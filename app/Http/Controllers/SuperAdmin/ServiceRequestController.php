<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\ServiceRequest;
use App\Models\PartnerProfile;
use App\Models\Service;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Log;

class ServiceRequestController extends Controller
{
    /**
     * Assign partner to a specific service request
     */
    public function assign(Request $request, $request_id)
    {
        try {
            $request->validate([
                'partner_id' => 'required|exists:partner_profiles,partner_id'
            ]);

            $serviceRequest = ServiceRequest::with(['orderItem.service', 'order'])
                ->findOrFail($request_id);

            // Same payment gate as OrderController::assignPartner: an unpaid
            // online-prepay order must not be handed to a partner from the
            // per-service dropdown either.
            $order = $serviceRequest->order;
            if ($order && $order->payment_method === 'online' && $order->payment_status !== 'paid') {
                $message = "This booking is unpaid (online prepay) and cannot be assigned to a partner yet. The customer must complete the payment first (payment_status: {$order->payment_status}).";

                if ($request->expectsJson()) {
                    return response()->json(['success' => false, 'message' => $message], 409);
                }

                return back()->withErrors(['error' => $message]);
            }

            // Verify partner is active and available
            $partner = PartnerProfile::with('user')
                ->where('partner_id', $request->partner_id)
                ->where('status', 'active')
                ->whereHas('user', function ($query) {
                    $query->where('is_active', true);
                })
                ->firstOrFail();

            // Admin override: the admin can assign any partner, even one who
            // does not list the service. Record a warning so the mismatch is
            // visible in logs instead of blocking the assignment outright.
            if (
                $serviceRequest->orderItem
                && $serviceRequest->orderItem->service
                && !$partner->services()->where('services.service_id', $serviceRequest->orderItem->service->service_id)->exists()
            ) {
                Log::warning('Admin assigned a partner who does not list the requested service', [
                    'request_id' => $request_id,
                    'partner_id' => $partner->partner_id,
                    'service_id' => $serviceRequest->orderItem->service->service_id,
                ]);
            }

            $serviceRequest->update([
                'partner_id' => $request->partner_id,
                'status' => 'assigned',
                'partner_assigned_at' => now(),
                'assignment_expires_at' => now()->addMinutes((int) $request->input('timeout_minutes', 30)),
                'assignment_timeout_minutes' => (int) $request->input('timeout_minutes', 30),
                'assignment_method' => 'admin_assigned',
                'assignment_notes' => $request->input('notes', 'Assigned by administrator'),
                'partner_accepted_at' => null,
                'partner_rejected_at' => null,
                'rejection_reason' => null,
            ]);

            $serviceRequest->order?->update(['status' => 'partner_assigned']);

            \App\Services\NotificationService::sendWithPush(
                (int) $partner->partner_id,
                'new_job',
                'New Job Available',
                "A new service job (#{$serviceRequest->order_id}) has been assigned to you.",
                ['order_id' => $serviceRequest->order_id, 'assignment_type' => 'admin_assigned']
            );

            Log::info('Partner assigned to service request', [
                'request_id' => $request_id,
                'partner_id' => $request->partner_id,
                'order_id' => $serviceRequest->order_id,
                'service_name' => $serviceRequest->orderItem->service->name ?? 'Unknown',
                'assigned_by' => 'admin'
            ]);

            $successMessage = "Partner \"{$partner->user->full_name}\" assigned to service request successfully!";

            // Return JSON for AJAX requests
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => true,
                    'status' => 'assigned',
                    'partner' => [
                        'id' => $partner->partner_id,
                        'name' => $partner->user->full_name,
                    ],
                    'assigned_at' => $serviceRequest->partner_assigned_at->format('M d, Y g:i A'),
                    'message' => $successMessage
                ]);
            }

            // Return redirect for form submissions
            return redirect()->back()
                ->with('success', $successMessage);
        } catch (\Exception $e) {
            Log::error('Failed to assign partner', [
                'request_id' => $request_id,
                'error' => $e->getMessage()
            ]);

            $errorMessage = 'Failed to assign partner: ' . $e->getMessage();

            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => $errorMessage
                ], 500);
            }

            return redirect()->back()
                ->with('error', $errorMessage);
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
                    $query->where('services.service_id', $service_id);
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
