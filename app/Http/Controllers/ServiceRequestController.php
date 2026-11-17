<?php

namespace App\Http\Controllers;

use App\Models\ServiceRequest;
use App\Models\PartnerProfile;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class ServiceRequestController extends Controller
{
    /**
     * Assign partner to a specific service request
     */
    public function assign(Request $request, $request_id): JsonResponse
    {
        try {
            $request->validate([
                'partner_id' => 'required|exists:partner_profiles,partner_id'
            ]);

            $serviceRequest = ServiceRequest::findOrFail($request_id);

            // Verify partner is active and available
            $partner = PartnerProfile::with('user')
                ->where('partner_id', $request->partner_id)
                ->where('status', 'active')
                ->firstOrFail();

            $serviceRequest->update([
                'partner_id' => $request->partner_id,
                'status' => 'assigned',
                'partner_assigned_at' => now(),
            ]);

            Log::info('Partner assigned to service request', [
                'request_id' => $request_id,
                'partner_id' => $request->partner_id,
                'assigned_by' => 'admin'
            ]);

            return response()->json([
                'success' => true,
                'status' => 'assigned',
                'partner' => [
                    'id' => $partner->partner_id,
                    'name' => $partner->user->full_name,
                ],
                'assigned_at' => $serviceRequest->partner_assigned_at->format('M d, Y g:i A')
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to assign partner', [
                'request_id' => $request_id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to assign partner: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get available partners for a specific service
     */
    public function getAvailablePartners(Request $request, $service_id): JsonResponse
    {
        try {
            $partners = PartnerProfile::with('user')
                ->where('status', 'active')
                ->whereHas('user', function ($query) {
                    $query->where('is_active', true);
                })
                ->whereHas('services', function ($query) use ($service_id) {
                    $query->where('service_id', $service_id);
                })
                ->get()
                ->map(function ($partner) {
                    return [
                        'partner_id' => $partner->partner_id,
                        'name' => $partner->user->full_name,
                        'rating' => $partner->average_rating ?? 0,
                        'total_jobs' => $partner->total_completed_jobs ?? 0
                    ];
                });

            return response()->json([
                'success' => true,
                'partners' => $partners
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch partners'
            ], 500);
        }
    }
}
