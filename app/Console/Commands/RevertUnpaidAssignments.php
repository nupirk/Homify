<?php

namespace App\Console\Commands;

use App\Models\Order;
use App\Models\ServiceRequest;
use App\Services\AuditService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * One-shot reconciliation for bookings created before the payment gate.
 *
 * Online-prepay orders used to be promoted to partner_assigned/confirmed by
 * the every-minute auto-assign job before the customer paid. This command
 * walks those "ghost" bookings - online + unpaid + in a live/assignable
 * status - and reverts them to 'created' so they stop looking booked until
 * the payment actually lands.
 *
 * Usage: php artisan bookings:revert-unpaid-assignments [--pretend]
 */
class RevertUnpaidAssignments extends Command
{
    protected $signature = 'bookings:revert-unpaid-assignments {--pretend : Show what would be reverted without changing anything}';

    protected $description = 'Revert unpaid online-prepay orders that were wrongly promoted to assigned/confirmed statuses';

    /** Statuses that mean "live job" and were wrongly reachable while unpaid. */
    private const LIVE_STATUSES = ['partner_assigned', 'confirmed', 'assigned', 'accepted'];

    public function handle(): int
    {
        $pretend = (bool) $this->option('pretend');

        $ghosts = Order::where('payment_method', 'online')
            ->whereIn('payment_status', ['pending', 'failed'])
            ->whereIn('status', self::LIVE_STATUSES)
            ->with('serviceRequests')
            ->orderBy('created_at')
            ->get();

        if ($ghosts->isEmpty()) {
            $this->info('No unpaid online bookings stuck in live statuses. Nothing to do.');
            return self::SUCCESS;
        }

        $this->info("Found {$ghosts->count()} unpaid online booking(s) stuck in live statuses.");

        $reverted = 0;
        $leftForReview = [];

        foreach ($ghosts as $order) {
            // Sanity: never touch orders whose payment already landed.
            if ($order->payment_status === 'paid') {
                continue;
            }

            // Jobs already in progress are NOT reverted - a partner may be on
            // site. They are reported for manual review instead.
            $working = $order->serviceRequests
                ->whereIn('status', ['working', 'in_progress'])
                ->isNotEmpty();

            if ($working || $order->status === 'in_progress') {
                $leftForReview[] = [
                    'order_id' => $order->order_id,
                    'status' => $order->status,
                    'reason' => 'work already in progress',
                ];
                $this->warn("Order #{$order->order_id} left for manual review (work in progress).");
                continue;
            }

            if ($pretend) {
                $this->line("Would revert Order #{$order->order_id} ({$order->status} -> created).");
                $reverted++;
                continue;
            }

            DB::transaction(function () use ($order) {
                // Release the partner hold: back to an unassigned pending
                // service request that nothing can promote until payment.
                ServiceRequest::where('order_id', $order->order_id)
                    ->where('status', '!=', 'completed')
                    ->update([
                        'status' => 'pending',
                        'partner_id' => null,
                        'partner_assigned_at' => null,
                        'assignment_expires_at' => null,
                        'assignment_timeout_minutes' => 30,
                    ]);

                $order->update(['status' => 'created']);

                AuditService::record(null, 'unpaid_booking_reverted', 'order', $order->order_id, [
                    'status' => $order->status,
                    'payment_status' => $order->payment_status,
                ], [
                    'status' => 'created',
                    'reason' => 'Reverted unpaid online-prepay booking that was wrongly marked as assigned/booked',
                    'command' => 'bookings:revert-unpaid-assignments',
                ]);

                Log::info('Reverted unpaid online booking to created', [
                    'order_id' => $order->order_id,
                    'previous_status' => $order->status,
                    'payment_status' => $order->payment_status,
                ]);
            });

            $this->line("Reverted Order #{$order->order_id} ({$order->status} -> created).");
            $reverted++;
        }

        $this->info("Done: {$reverted} reverted" . ($pretend ? ' (pretend mode - no changes made)' : '') . '.');

        if ($leftForReview) {
            $this->warn('Orders left for manual review (work in progress):');
            foreach ($leftForReview as $item) {
                $this->warn("  #{$item['order_id']} - {$item['status']} ({$item['reason']})");
            }
        }

        return self::SUCCESS;
    }
}