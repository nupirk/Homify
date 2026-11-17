<?php

namespace App\Http\Controllers\Api\V1;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

/**
 * Deep health check for uptime monitors and deploy gates.
 *
 * Unlike Laravel's built-in /up (which only proves the process booted), this
 * actually exercises the dependencies the app cannot serve without: the
 * database, the cache store and a writable storage disk. It returns 503 the
 * moment a critical component is broken so a load balancer can pull the node.
 *
 * No secrets, hosts or driver internals are exposed — only a component name
 * and a status.
 */
class HealthController
{
    public function index(): JsonResponse
    {
        $checks = [
            'database' => $this->checkDatabase(),
            'cache' => $this->checkCache(),
            'storage' => $this->checkStorage(),
        ];

        $healthy = !in_array('fail', array_column($checks, 'status'), true);

        return response()->json([
            'status' => $healthy ? 'ok' : 'degraded',
            'checks' => $checks,
            'timestamp' => now()->toIso8601String(),
        ], $healthy ? 200 : 503);
    }

    /** @return array{status: string, latency_ms?: float} */
    private function checkDatabase(): array
    {
        $start = microtime(true);

        try {
            DB::select('SELECT 1');
        } catch (Throwable) {
            return ['status' => 'fail'];
        }

        return [
            'status' => 'ok',
            'latency_ms' => round((microtime(true) - $start) * 1000, 2),
        ];
    }

    /** @return array{status: string} */
    private function checkCache(): array
    {
        try {
            $key = 'health:' . Str::random(8);
            Cache::put($key, '1', 5);
            $value = Cache::get($key);
            Cache::forget($key);

            return ['status' => $value === '1' ? 'ok' : 'fail'];
        } catch (Throwable) {
            return ['status' => 'fail'];
        }
    }

    /** @return array{status: string} */
    private function checkStorage(): array
    {
        try {
            $path = storage_path('framework');
            if (!is_dir($path) || !is_writable($path)) {
                return ['status' => 'fail'];
            }

            return ['status' => 'ok'];
        } catch (Throwable) {
            return ['status' => 'fail'];
        }
    }
}
