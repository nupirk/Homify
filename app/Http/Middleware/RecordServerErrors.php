<?php

namespace App\Http\Middleware;

use App\Models\ErrorLog;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * Every server-side 5xx is recorded twice: once in the Laravel log (where
 * whoever debugs actually looks) and once in `error_logs` (where the admin
 * dashboard lists it).
 *
 * Controllers here catch their own exceptions and return a JSON 500 without
 * logging anything, which is why failures looked like they had "no logs at
 * all". Uncaught exceptions are still logged by Laravel itself; this middleware
 * only mirrors them so the dashboard has them too.
 */
class RecordServerErrors
{
    public function handle(Request $request, Closure $next): Response
    {
        $startedAt = microtime(true);

        try {
            $response = $next($request);
        } catch (\Throwable $e) {
            // Re-thrown so the framework still renders and logs it.
            $this->mirror($request, 500, $e, $startedAt);

            throw $e;
        }

        $status = $response->getStatusCode();

        if ($status >= 500) {
            $this->logToFile($request, $status, null, $startedAt);
            $this->mirror($request, $status, null, $startedAt);
        }

        return $response;
    }

    private function logToFile(Request $request, int $status, ?\Throwable $e, float $startedAt): void
    {
        if ($this->isErrorIngest($request)) {
            return;
        }

        try {
            Log::error('Server error', array_filter([
                'status' => $status,
                'method' => $request->method(),
                'path' => '/' . ltrim($request->path(), '/'),
                'route' => $request->route()?->getName(),
                'ip' => $request->ip(),
                'user_id' => $request->user()?->getAuthIdentifier(),
                'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
                'exception' => $e ? get_class($e) : null,
                'error' => $e?->getMessage(),
            ]));
        } catch (\Throwable) {
            // Logging must never turn into a second failure.
        }
    }

    private function mirror(Request $request, int $status, ?\Throwable $e, float $startedAt): void
    {
        if ($this->isErrorIngest($request)) {
            return;
        }

        try {
            $method = strtoupper($request->method());
            $path = '/' . ltrim($request->path(), '/');
            $exception = $e ? get_class($e) : 'Http' . $status;

            $message = $e
                ? class_basename($e) . ': ' . $e->getMessage()
                : "HTTP {$status} on {$method} {$path}";

            // Same dedupe contract as the client ingest: the same failure at the
            // same place bumps `occurrences` instead of flooding the list.
            $fingerprint = hash('sha256', implode('|', ['server', $method, $path, (string) $status, $exception]));

            $now = now();

            $affected = DB::table('error_logs')
                ->where('fingerprint', $fingerprint)
                ->where('last_seen_at', '>=', $now->copy()->subHours(24))
                ->update([
                    'occurrences' => DB::raw('occurrences + 1'),
                    'last_seen_at' => $now,
                ]);

            if ($affected === 0) {
                ErrorLog::create([
                    'fingerprint' => $fingerprint,
                    'level' => 'error',
                    'message' => substr($message, 0, 2000),
                    'exception_class' => $e ? class_basename($e) : null,
                    'file' => $e?->getFile() ? basename($e->getFile()) : null,
                    'line' => $e?->getLine(),
                    'stack' => $e?->getTraceAsString(),
                    'context' => array_filter([
                        'status' => $status,
                        'method' => $method,
                        'path' => $path,
                        'route' => $request->route()?->getName(),
                        'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
                    ], static fn ($value) => $value !== null),
                    'screen' => substr((string) ($request->route()?->getName() ?? $path), 0, 120),
                    // Distinguishes server failures from app crashes in the portal.
                    'platform' => 'server',
                    'user_id' => $request->user()?->getAuthIdentifier(),
                    'ip_address' => $request->ip(),
                    'user_agent' => substr((string) $request->userAgent(), 0, 255) ?: null,
                    'occurrences' => 1,
                    'first_seen_at' => $now,
                    'last_seen_at' => $now,
                    'created_at' => $now,
                ]);
            }
        } catch (\Throwable) {
            // A broken database must not break the response that reports it.
        }
    }

    private function isErrorIngest(Request $request): bool
    {
        return $request->is('api/v1/errors') || $request->is('errors');
    }
}
