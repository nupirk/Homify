<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\AnalyticsEvent;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class AnalyticsController extends Controller
{
    /** Funnel steps reported by the mobile app, in order. */
    private const FUNNEL = [
        'app_open',
        'service_view',
        'add_to_cart',
        'checkout_start',
        'payment_initiated',
        'payment_success',
        'booking_confirmed',
    ];

    private const ALLOWED_EVENTS = [
        'app_open',
        'service_view',
        'add_to_cart',
        'checkout_start',
        'payment_initiated',
        'payment_success',
        'payment_failed',
        'booking_confirmed',
        'booking_cancelled',
        'partner_assigned',
        'partner_job_accept',
        'partner_job_complete',
        'promo_applied',
        'referral_code_redeemed',
    ];

    /**
     * Batch ingest. Public (pre-login events must report) but throttled
     * and sanitized — user_id is resolved server-side from the token only.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'session_id' => 'nullable|string|max:64',
            'platform'   => 'nullable|string|max:32',
            'app_version'=> 'nullable|string|max:32',
            'events'     => 'required|array|max:50',
            'events.*.name' => 'required|string|max:64',
            'events.*.props' => 'nullable|array|max:20',
            'events.*.ts' => 'nullable|integer',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'message' => $validator->errors()->first()], 422);
        }

        // Optional auth: attach the user when a valid token rides along,
        // without requiring login (app_open fires pre-auth).
        $userId = Auth::guard('sanctum')->user()?->user_id;
        $now = now();
        $rows = [];
        $dropped = 0;

        foreach ($validator->validated()['events'] as $e) {
            // Allow-list only: unknown names would otherwise let a client fill
            // the funnel table with arbitrary rows.
            if (!in_array($e['name'], self::ALLOWED_EVENTS, true)) {
                $dropped++;
                continue;
            }

            $rows[] = [
                'event' => substr($e['name'], 0, 64),
                'user_id' => $userId,
                'session_id' => $validator->validated()['session_id'] ?? null,
                'platform' => $validator->validated()['platform'] ?? null,
                'app_version' => $validator->validated()['app_version'] ?? null,
                'props' => json_encode($this->sanitizeProps($e['props'] ?? [])),
                'created_at' => $now,
            ];
        }

        if ($rows) {
            AnalyticsEvent::insert($rows);
        }

        return response()->json([
            'success' => true,
            'message' => 'accepted',
            'accepted' => count($rows),
            'dropped' => $dropped,
        ]);
    }

    /** Funnel counts + step conversion for the admin dashboard. */
    public function funnel(Request $request)
    {
        $days = min(max((int) $request->query('days', 30), 1), 90);
        $since = now()->subDays($days);

        $counts = AnalyticsEvent::query()
            ->where('created_at', '>=', $since)
            ->groupBy('event')
            ->selectRaw('event, COUNT(*) as total, COUNT(DISTINCT user_id) as users')
            ->orderByDesc('total')
            ->pluck('total', 'event');

        $steps = [];
        $prev = null;
        $baseline = (int) ($counts['app_open'] ?? 0);
        foreach (self::FUNNEL as $name) {
            $total = (int) ($counts[$name] ?? 0);
            $steps[] = [
                'event' => $name,
                'count' => $total,
                'step_conversion' => $prev === null || $prev === 0
                    ? null
                    : round($total / $prev, 3),
                'overall_conversion' => $baseline === 0 || $name === 'app_open'
                    ? null
                    : round($total / $baseline, 3),
            ];
            $prev = $total;
        }

        $errors = (int) ($counts['payment_failed'] ?? 0);
        $successes = (int) ($counts['payment_success'] ?? 0);

        return response()->json([
            'success' => true,
            'data' => [
                'days' => $days,
                'funnel' => $steps,
                'payment' => [
                    'success' => $successes,
                    'failed' => $errors,
                    'success_rate' => ($successes + $errors) === 0 ? null
                        : round($successes / ($successes + $errors), 3),
                ],
                'other_events' => $counts->except(self::FUNNEL)
                    ->map(fn ($total, $event) => ['event' => $event, 'count' => (int) $total])
                    ->values(),
            ],
        ]);
    }

    private function sanitizeProps(array $props): array
    {
        $clean = [];
        foreach ($props as $k => $v) {
            if (!is_string($k) || strlen($k) > 40) continue;
            if (is_scalar($v)) {
                $clean[substr($k, 0, 40)] = is_string($v) ? substr($v, 0, 300) : $v;
            }
            if (count($clean) >= 20) break;
        }
        return $clean;
    }
}
