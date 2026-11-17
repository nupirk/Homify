<?php

namespace App\Console\Commands;

use App\Models\ServiceRequest;
use App\Models\Order;
use App\Models\PartnerProfile;
use App\Services\AuditService;
use App\Services\AvailabilityService;
use App\Services\NotificationService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class HandleExpiredAssignments extends Command
{
    protected $signature = 'bookings:handle-expired-assignments';
    protected $description = 'Handle partner assignments that have expired without response';

    public function handle()
    {
        $this->info('Checking for expired partner assignments...');

        // Find assignments that have expired (assigned status, past expiration time).
        // Payment gate: online-prepay orders that haven't been paid are NOT
        // live jobs - their pre-attached partner holds a slot reservation that
        // is refreshed (and notified) only when the payment lands. They must
        // never be auto-reassigned or bounced back to 'created' while unpaid.
        $expiredAssignments = ServiceRequest::where('status', 'assigned')
            ->whereNotNull('assignment_expires_at')
            ->where('assignment_expires_at', '<', now())
            ->whereNull('partner_accepted_at')
            ->whereNull('partner_rejected_at')
            ->whereHas('order', function ($q) {
                $q->where(function ($q) {
                    $q->where('payment_method', 'pas')
                        ->orWhere('payment_status', 'paid');
                });
            })
            ->with(['order', 'partner.user'])
            ->get();

        if ($expiredAssignments->count() === 0) {
            $this->info('No expired assignments found.');
            return;
        }

        $this->info("Found {$expiredAssignments->count()} expired assignments.");

        foreach ($expiredAssignments as $serviceRequest) {
            try {
                $this->handleExpiredAssignment($serviceRequest);
                $this->info("Processed expired assignment for Order #{$serviceRequest->order_id}");
            } catch (\Exception $e) {
                $this->error("Failed to process expired assignment for Order #{$serviceRequest->order_id}: {$e->getMessage()}");
                Log::error('Failed to handle expired assignment', [
                    'service_request_id' => $serviceRequest->request_id,
                    'order_id' => $serviceRequest->order_id,
                    'error' => $e->getMessage()
                ]);
            }
        }

        $this->info('Expired assignment processing completed.');
    }

    private function handleExpiredAssignment(ServiceRequest $serviceRequest)
    {
        DB::transaction(function () use ($serviceRequest) {
            // Lock the row and re-verify: an accept/reject may have landed
            // between the query at the top of the run and this transaction.
            $locked = ServiceRequest::whereKey($serviceRequest->request_id)
                ->lockForUpdate()
                ->first();

            if (
                !$locked
                || $locked->status !== 'assigned'
                || $locked->partner_accepted_at
                || $locked->partner_rejected_at
            ) {
                return;
            }

            $serviceRequest = $locked;
            $partnerName = $serviceRequest->partner->user->full_name ?? 'Unknown Partner';
            $previousPartnerId = $serviceRequest->partner_id;
            $timeoutMinutes = $serviceRequest->assignment_timeout_minutes;
            $reassignmentCount = $serviceRequest->reassignment_count + 1;

            // Update service request - remove expired assignment
            $serviceRequest->update([
                'status' => 'pending',
                'partner_id' => null,
                'partner_assigned_at' => null,
                'assignment_expires_at' => null,
                'assignment_timeout_minutes' => null,
                'assignment_method' => null,
                'assignment_notes' => "Assignment expired - partner did not respond within timeout period. Previous partner: {$partnerName}",
                'reassignment_count' => $reassignmentCount,
                'reassignment_history' => array_merge(
                    $serviceRequest->reassignment_history ?? [],
                    [[
                        'reason' => 'timeout_expired',
                        'previous_partner_id' => $previousPartnerId,
                        'previous_partner_name' => $partnerName,
                        'expired_at' => now()->toISOString(),
                        'timeout_minutes' => $timeoutMinutes
                    ]]
                )
            ]);

            // Only reset the order if no other request on it is still being
            // worked - a multi-service order must keep its live assignment.
            $otherActive = $serviceRequest->order->serviceRequests()
                ->where('request_id', '!=', $serviceRequest->request_id)
                ->whereIn('status', ['assigned', 'accepted', 'in_progress', 'working'])
                ->exists();

            if (!$otherActive) {
                $serviceRequest->order->update(['status' => 'created']);
            }

            // Log the timeout
            Log::info('Partner assignment expired due to timeout', [
                'order_id' => $serviceRequest->order_id,
                'partner_id' => $previousPartnerId,
                'partner_name' => $partnerName,
                'assigned_at' => $serviceRequest->partner_assigned_at,
                'expired_at' => now(),
                'timeout_minutes' => $timeoutMinutes,
                'reassignment_count' => $reassignmentCount
            ]);

            AuditService::record(null, 'assignment_expired', 'order', $serviceRequest->order_id, [
                'status' => 'assigned',
                'partner_id' => $previousPartnerId,
            ], [
                'status' => 'pending',
                'reason' => 'timeout_expired',
                'reassignment_count' => $reassignmentCount,
            ]);

            // Notify customer about the delay
            NotificationService::send(
                $serviceRequest->order->user_id,
                'booking_updated',
                'Finding you a new partner',
                "Your previous partner did not respond to booking #{$serviceRequest->order_id} in time. We are finding you a new partner.",
                ['order_id' => $serviceRequest->order_id]
            );

            // Notify admins about the expired assignment
            NotificationService::sendToAdmins(
                'assignment_expired',
                'Partner assignment expired',
                "Partner {$partnerName} did not respond to job #{$serviceRequest->order_id} in time.",
                ['order_id' => $serviceRequest->order_id, 'partner_id' => $serviceRequest->partner_id]
            );

            // Auto-reassign to the next best partner
            $reassigned = $this->autoReassign($serviceRequest);

            if (!$reassigned) {
                NotificationService::sendToAdmins(
                    'reassignment',
                    'No partner available for reassignment',
                    "No available partner found for order #{$serviceRequest->order_id}. Manual intervention needed.",
                    ['order_id' => $serviceRequest->order_id]
                );

                NotificationService::send(
                    $serviceRequest->order->user_id,
                    'booking_updated',
                    'No partner available yet',
                    "We couldn't find a partner for your booking #{$serviceRequest->order_id} yet. You'll be notified as soon as one is available.",
                    ['order_id' => $serviceRequest->order_id]
                );
            }
        });
    }

    /**
     * Auto-reassign an order to the next best available partner.
     */
    private function autoReassign(ServiceRequest $serviceRequest): bool
    {
        // Stop after 3 attempts: further auto-assignment only re-notifies the
        // same partners and spams the customer; it needs human intervention.
        if ($serviceRequest->reassignment_count >= 3) {
            Log::info('Auto-reassignment skipped - attempt limit reached', [
                'order_id' => $serviceRequest->order_id,
                'reassignment_count' => $serviceRequest->reassignment_count,
            ]);
            return false;
        }

        $order = $serviceRequest->order;
        $serviceId = $serviceRequest->orderItem?->service_id;

        if (!$serviceId) {
            Log::warning('Cannot auto-reassign: no service found for service request', [
                'service_request_id' => $serviceRequest->request_id,
                'order_id' => $serviceRequest->order_id
            ]);
            return false;
        }

        // Partners already tried (from reassignment history)
        $triedPartnerIds = collect($serviceRequest->reassignment_history ?? [])
            ->pluck('previous_partner_id')
            ->filter()
            ->values();

        $start = $serviceRequest->service_date
            ->copy()
            ->setTimeFromTimeString($serviceRequest->start_time->format('H:i'));

        $availability = app(AvailabilityService::class);
        $duration = $availability->durationForServices([$serviceId]);

        $candidates = PartnerProfile::query()
            ->where('status', 'active')
            ->whereHas('user', function ($q) {
                $q->where('is_active', true);
            })
            ->whereHas('services', function ($q) use ($serviceId) {
                $q->where('services.service_id', $serviceId);
            })
            ->when($triedPartnerIds->isNotEmpty(), function ($q) use ($triedPartnerIds) {
                $q->whereNotIn('partner_id', $triedPartnerIds->toArray());
            })
            ->orderByRaw('rating_avg DESC NULLS LAST')
            ->orderByDesc('total_ratings')
            ->limit(50)
            ->get();

        $candidate = $candidates->first(fn (PartnerProfile $partner) => $availability
            ->check($partner->partner_id, $start, $duration, $serviceRequest->request_id)['available']);

        if (!$candidate) {
            Log::info('No candidate partner found for auto-reassignment', [
                'order_id' => $serviceRequest->order_id,
                'service_id' => $serviceId
            ]);
            return false;
        }

        $candidateId = $candidate->partner_id;
        $previousPartnerName = collect($serviceRequest->reassignment_history ?? [])
            ->last()['previous_partner_name'] ?? 'Unknown';

        // Reassign
        $serviceRequest->update([
            'status' => 'assigned',
            'partner_id' => $candidateId,
            'partner_assigned_at' => now(),
            'assignment_expires_at' => now()->addMinutes(5),
            'assignment_timeout_minutes' => 5,
            'assignment_method' => 'auto_reassigned',
            'assignment_notes' => "Auto-reassigned after partner timeout. Previous partner: {$previousPartnerName}",
        ]);

        $order->update(['status' => 'partner_assigned']);

        Log::info('Auto-reassigned expired assignment', [
            'order_id' => $serviceRequest->order_id,
            'service_request_id' => $serviceRequest->request_id,
            'new_partner_id' => $candidateId,
            'reassignment_count' => $serviceRequest->reassignment_count + 1
        ]);

        AuditService::record(null, 'auto_reassigned', 'order', $serviceRequest->order_id, [
            'status' => 'pending',
        ], [
            'status' => 'partner_assigned',
            'partner_id' => $candidateId,
            'method' => 'auto_reassigned',
        ]);

        NotificationService::sendWithPush(
            $candidateId,
            'new_job',
            'New Job Available',
            "A new service job (#{$serviceRequest->order_id}) has been assigned to you. Please accept it within 5 minutes.",
            ['order_id' => $serviceRequest->order_id, 'assignment_type' => 'auto_reassigned']
        );

        return true;
    }
}
