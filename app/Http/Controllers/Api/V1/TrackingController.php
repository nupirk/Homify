<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\PartnerProfile;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TrackingController extends Controller
{
    /** Partner pings older than this are treated as stale (not shown to customers). */
    private const FRESH_SECONDS = 120;

    /** Order states where a customer may see the partner's live position. */
    private const TRACKABLE_STATUSES = [
        'partner_assigned',
        'assigned',
        'accepted',
        'in_progress',
        'working',
        'pending_reassignment',
    ];

    /** Terminal states: the job is over, no live position may leak. */
    private const TERMINAL_STATUSES = [
        'completed',
        'cancelled',
        'cancelled_by_customer',
        'cancelled_by_partner',
        'cancelled_by_admin',
        'rejected',
        'closed',
        'refunded',
    ];

    /**
     * Partner location heartbeat. Called by the partner app while jobs are active.
     * Validates Nepal bounds and stamps live_updated_at.
     */
    public function heartbeat(Request $request): JsonResponse
    {
        if ($request->user()->user_type !== 'partner') {
            return response()->json([
                'success' => false,
                'message' => 'Partner access required',
            ], 403);
        }

        $validated = $request->validate([
            'latitude'  => 'required|numeric|between:26.0,31.0',
            'longitude' => 'required|numeric|between:79.5,89.0',
        ]);

        $profile = $request->user()->partnerProfile;

        if (!$profile) {
            $profile = PartnerProfile::create([
                'partner_id'    => $request->user()->user_id,
                'status'        => 'onboarding',
                'rating_avg'    => null,
                'total_ratings' => 0,
            ]);
        }

        $profile->update([
            'live_lat'        => (float) $validated['latitude'],
            'live_lng'        => (float) $validated['longitude'],
            'live_updated_at' => now(),
        ]);

        return response()->json([
            'success'    => true,
            'message'    => 'Location updated',
            'data'       => [
                'latitude'   => (float) $validated['latitude'],
                'longitude'  => (float) $validated['longitude'],
                'updated_at' => now()->toIso8601String(),
            ],
        ]);
    }

    /**
     * Customer polling endpoint: order + service status, assigned partner,
     * the partner's fresh live coordinates (only while the job is active),
     * and the destination address coordinates for the map.
     */
    public function show(Request $request, $orderId): JsonResponse
    {
        $order = Order::with([
            'serviceRequests.address',
            'serviceRequests.partner.user',
        ])
            ->where('user_id', $request->user()->user_id)
            ->where('order_id', $orderId)
            ->firstOrFail();

        $serviceRequest = $order->serviceRequests->first();
        $partner = $serviceRequest?->partner;
        $address = $serviceRequest?->address;

        $terminal = in_array($order->status, self::TERMINAL_STATUSES, true);
        $trackable = !$terminal && (
            in_array($order->status, self::TRACKABLE_STATUSES, true)
            || in_array($serviceRequest?->status, ['assigned', 'accepted', 'working', 'in_progress'], true)
        );

        $partnerLocation = null;
        if ($trackable && $partner && $partner->live_updated_at
            && $partner->live_updated_at->gt(now()->subSeconds(self::FRESH_SECONDS))) {
            $partnerLocation = [
                'latitude'   => (float) $partner->live_lat,
                'longitude'  => (float) $partner->live_lng,
                'updated_at' => $partner->live_updated_at->toIso8601String(),
            ];
        }

        return response()->json([
            'success' => true,
            'data'    => [
                'order_id'        => $order->order_id,
                'status'          => $order->status,
                'service_status'  => $serviceRequest?->status ?? null,
                'scheduled_at'    => $serviceRequest?->service_date,
                'server_time'     => now()->toIso8601String(),
                'partner'         => $partner ? [
                    'partner_id' => $partner->partner_id,
                    'name'       => $partner->user
                        ? trim($partner->user->first_name . ' ' . $partner->user->last_name)
                        : null,
                    'avatar_url' => $partner->user?->avatar_url,
                    'rating_avg' => (float) ($partner->rating_avg ?? 0),
                ] : null,
                'partner_location' => $partnerLocation,
                'destination'      => ($address && $address->latitude !== null && $address->longitude !== null)
                    ? [
                        'latitude'  => (float) $address->latitude,
                        'longitude' => (float) $address->longitude,
                        'address'   => $address->full_address,
                    ]
                    : null,
            ],
        ]);
    }
}
