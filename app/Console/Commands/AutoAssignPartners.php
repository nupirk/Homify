<?php

namespace App\Console\Commands;

use App\Models\PartnerProfile;
use App\Models\ServiceRequest;
use App\Services\AuditService;
use App\Services\AvailabilityService;
use App\Services\NotificationService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AutoAssignPartners extends Command
{
    protected $signature = 'bookings:auto-assign';
    protected $description = 'Auto-assign pending service requests to the best available partner';

    public function handle()
    {
        $this->info('Looking for pending service requests to auto-assign...');

        $pendingRequests = ServiceRequest::where('status', 'pending')
            ->whereNull('partner_id')
            // Cap auto-assignment attempts so a job nobody picks up stops
            // cycling through auto-assign -> timeout -> auto-assign (and stops
            // re-notifying the same partners) once the limit is reached.
            ->where('reassignment_count', '<', 3)
            // Payment gate: an ONLINE-prepay order that has NOT been paid must
            // never be auto-assigned - that would promote the order to
            // partner_assigned and notify a partner for money that never
            // arrived. Only PAS orders (settled at the door) or paid online
            // orders are assignable.
            ->whereHas('order', function ($q) {
                $q->where(function ($q) {
                    $q->where('payment_method', 'pas')
                        ->orWhere('payment_status', 'paid');
                });
            })
            ->whereHas('orderItem')
            ->with(['order'])
            ->orderBy('created_at', 'asc')
            ->get();

        if ($pendingRequests->count() === 0) {
            $this->info('No pending service requests found.');
            return;
        }

        $this->info("Found {$pendingRequests->count()} pending service request(s).");

        $assigned = 0;
        $skipped = 0;

        foreach ($pendingRequests as $serviceRequest) {
            try {
                if ($this->assignBestPartner($serviceRequest)) {
                    $assigned++;
                } else {
                    $skipped++;
                }
            } catch (\Exception $e) {
                $this->error("Failed to auto-assign Order #{$serviceRequest->order_id}: {$e->getMessage()}");
                Log::error('Auto-assign failed', [
                    'service_request_id' => $serviceRequest->request_id,
                    'order_id' => $serviceRequest->order_id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $this->info("Auto-assign complete: {$assigned} assigned, {$skipped} awaiting partners.");
    }

    /**
     * Assign the best available partner to a pending service request.
     */
    private function assignBestPartner(ServiceRequest $serviceRequest): bool
    {
        $serviceId = $serviceRequest->orderItem?->service_id;

        if (!$serviceId) {
            Log::warning('Cannot auto-assign: no service found for service request', [
                'service_request_id' => $serviceRequest->request_id,
                'order_id' => $serviceRequest->order_id,
            ]);
            return false;
        }

        $start = $serviceRequest->service_date
            ->copy()
            ->setTimeFromTimeString($serviceRequest->start_time->format('H:i'));

        $availability = app(AvailabilityService::class);
        $duration = $availability->durationForServices([$serviceId]);

        // Partners already tried for this job (from reassignment history) must not
        // be re-assigned - otherwise the same partner who ignored the job gets the
        // same "New Job Available" notification on every auto-assign tick.
        $triedPartnerIds = collect($serviceRequest->reassignment_history ?? [])
            ->pluck('previous_partner_id')
            ->filter()
            ->values();

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

        // Working hours + overlapping bookings are resolved in one place so the
        // cron, search and checkout all agree on who is free.
        $candidate = $candidates->first(fn (PartnerProfile $partner) => $availability
            ->check($partner->partner_id, $start, $duration, $serviceRequest->request_id)['available']);

        if (!$candidate) {
            Log::info('No available partner found for auto-assignment', [
                'order_id' => $serviceRequest->order_id,
                'service_id' => $serviceId,
            ]);
            return false;
        }

        $candidateId = $candidate->partner_id;

        // Re-check under a row lock: another worker (or an admin) may have
        // assigned the request since it was fetched at the top of the run.
        $assigned = DB::transaction(function () use ($serviceRequest, $candidateId) {
            $locked = ServiceRequest::whereKey($serviceRequest->request_id)
                ->lockForUpdate()
                ->first();

            if (!$locked || $locked->status !== 'pending' || $locked->partner_id !== null) {
                return false;
            }

            $locked->update([
                'status' => 'assigned',
                'partner_id' => $candidateId,
                'partner_assigned_at' => now(),
                'assignment_expires_at' => now()->addMinutes(5),
                'assignment_timeout_minutes' => 5,
                'assignment_method' => 'auto_assigned',
                'assignment_notes' => 'Auto-assigned by the system to the best available partner',
            ]);

            $locked->order->update(['status' => 'partner_assigned']);

            return true;
        });

        if (!$assigned) {
            Log::info('Auto-assign skipped, request was claimed by another worker', [
                'order_id' => $serviceRequest->order_id,
                'service_request_id' => $serviceRequest->request_id,
            ]);
            return false;
        }

        Log::info('Auto-assigned pending service request', [
            'order_id' => $serviceRequest->order_id,
            'service_request_id' => $serviceRequest->request_id,
            'partner_id' => $candidateId,
            'service_id' => $serviceId,
        ]);

        AuditService::record(null, 'auto_assigned', 'order', $serviceRequest->order_id, [
            'status' => 'pending',
        ], [
            'status' => 'partner_assigned',
            'partner_id' => $candidateId,
            'method' => 'auto_assigned',
        ]);

        NotificationService::sendWithPush(
            $candidateId,
            'new_job',
            'New Job Available',
            "A new service job (#{$serviceRequest->order_id}) has been assigned to you. Please accept it within 5 minutes.",
            ['order_id' => $serviceRequest->order_id, 'assignment_type' => 'auto_assigned']
        );

        $this->info("Assigned Order #{$serviceRequest->order_id} to partner #{$candidateId}.");

        return true;
    }
}
