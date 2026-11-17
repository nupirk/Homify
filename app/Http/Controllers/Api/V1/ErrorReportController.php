<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\ErrorLog;
use App\Support\Redactor;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

/**
 * Client error/crash ingest.
 *
 * Public but throttled (the app can crash before/without login), sanitized on
 * the way in, and de-duplicated by fingerprint so the admin list stays a list
 * of distinct problems. Anything sensitive in `context` is redacted using the
 * same key list the server logger uses.
 */
class ErrorReportController extends Controller
{
    private const LEVELS = ['debug', 'info', 'warning', 'error', 'fatal'];

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'message'          => 'required|string|max:2000',
            'level'            => 'nullable|string|max:20',
            'exception_class'  => 'nullable|string|max:191',
            'file'             => 'nullable|string|max:255',
            'line'             => 'nullable|integer|min:0|max:100000000',
            'stack'            => 'nullable|string|max:8000',
            'context'          => 'nullable|array|max:30',
            'screen'           => 'nullable|string|max:120',
            'platform'         => 'nullable|string|max:32',
            'app_version'      => 'nullable|string|max:32',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'message' => $validator->errors()->first()], 422);
        }

        $data = $validator->validated();
        $level = in_array($data['level'] ?? '', self::LEVELS, true) ? $data['level'] : 'error';

        $context = Redactor::redactKeys(
            $this->sanitizeContext($data['context'] ?? []),
            Redactor::sensitiveKeys()
        );

        $message = trim($data['message']);
        $exceptionClass = $data['exception_class'] ?? null;
        $screen = $data['screen'] ?? null;

        // Same class + message + screen = same problem, regardless of who hit it.
        $fingerprint = hash('sha256', implode('|', [
            $exceptionClass ?? '',
            $screen ?? '',
            $this->normalizeMessage($message),
        ]));

        $userId = Auth::guard('sanctum')->user()?->user_id;
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
                'level' => $level,
                'message' => substr($message, 0, 2000),
                'exception_class' => $exceptionClass,
                'file' => $data['file'] ?? null,
                'line' => $data['line'] ?? null,
                'stack' => isset($data['stack']) ? substr($data['stack'], 0, 8000) : null,
                'context' => $context,
                'screen' => $screen,
                'platform' => $data['platform'] ?? null,
                'app_version' => $data['app_version'] ?? null,
                'user_id' => $userId,
                'ip_address' => $request->ip(),
                'user_agent' => substr((string) $request->userAgent(), 0, 255) ?: null,
                'occurrences' => 1,
                'first_seen_at' => $now,
                'last_seen_at' => $now,
                'created_at' => $now,
            ]);
        }

        return response()->json(['success' => true, 'message' => 'accepted'], 202);
    }

    /** Admin list: most recently active first. */
    public function index(Request $request)
    {
        $query = ErrorLog::query()->orderByDesc('last_seen_at');

        if ($request->filled('level')) {
            $query->where('level', $request->query('level'));
        }

        $perPage = min(max((int) $request->query('per_page', 25), 1), 100);

        return response()->json([
            'success' => true,
            'data' => $query->paginate($perPage),
        ]);
    }

    /** Drop anything non-scalar and cap key/value sizes. */
    private function sanitizeContext(array $context): array
    {
        $clean = [];
        foreach ($context as $key => $value) {
            if (!is_string($key) || strlen($key) > 40) {
                continue;
            }
            if (is_scalar($value) || $value === null) {
                $clean[substr($key, 0, 40)] = is_string($value) ? substr($value, 0, 300) : $value;
            }
            if (count($clean) >= 30) {
                break;
            }
        }
        return $clean;
    }

    /**
     * Strip volatile tokens (ids, numbers, quoted values) so the same bug
     * raised with different data still collapses to one fingerprint.
     */
    private function normalizeMessage(string $message): string
    {
        $normalized = preg_replace('/[0-9]+/', '#', $message);
        $normalized = preg_replace('/\s+/', ' ', (string) $normalized);

        return strtolower(trim(substr((string) $normalized, 0, 300)));
    }
}
