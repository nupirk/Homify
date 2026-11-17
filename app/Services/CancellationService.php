<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Payment;
use App\Models\ServiceRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Single cancellation path for bookings.
 *
 * Every cancellation flow (customer, admin, payment timeout, payment
 * failure) funnels through here so the order, its service requests, its
 * payments and the ledger always move together:
 *
 *  - order status -> cancelled_by_{customer|admin|timeout|system}
 *  - service requests -> cancelled
 *  - online-paid bookings -> REFUND ledger entries + payment_status=refunded
 *    + a "refund required" marker for the manual gateway refund queue
 *  - cash-settled PAS bookings -> never refunded (money already collected)
 */
class CancellationService
{
    /**
     * @return array{success: bool, message: string, refunded: bool, refund_required: bool}
     */
    public function cancel(Order $order, string $cancelledBy, string $reason, ?int $actorUserId = null): array
    {
        $cancelledBy = in_array($cancelledBy, ['customer', 'admin', 'timeout', 'system'])
            ? $cancelledBy
            : 'system';

        $nonCancellable = [
            'in_progress', 'working', 'completed', 'cancelled',
            'cancelled_by_customer', 'cancelled_by_partner', 'cancelled_by_admin', 'refunded',
        ];

        return DB::transaction(function () use ($order, $cancelledBy, $reason, $actorUserId, $nonCancellable) {
            // Compare-and-swap: serialize concurrent cancellation/expiry/
            // completion against this order.
            $locked = Order::whereKey($order->order_id)->lockForUpdate()->first();

            if (!$locked) {
                return [
                    'success' => false,
                    'message' => 'Booking not found',
                    'refunded' => false,
                    'refund_required' => false,
                ];
            }

            if (in_array($locked->status, $nonCancellable)) {
                return [
                    'success' => false,
                    'message' => 'Cannot cancel booking in current status: ' . ucfirst(str_replace('_', ' ', $locked->status)),
                    'refunded' => false,
                    'refund_required' => false,
                ];
            }

            // Refund gateway-paid bookings only. Cash-settled PAS orders are
            // excluded: the partner already collected the cash, and a refund
            // would be a double payout.
            $wasPaidOnline = $locked->payment_status === 'paid' && $locked->payment_received !== 'cash';
            $refundRequired = false;

            if ($wasPaidOnline) {
                $ledgerService = app(\App\Services\LedgerService::class);
                $ledgerService->recordRefund(
                    $locked->order_id,
                    $locked->user_id,
                    (float) $locked->total_amount,
                    ['reason' => $reason]
                );

                // Flag the manual gateway refund (eSewa/Khalti have no
                // automatic refund API) - surfaced to the admin queue via
                // admin_notes + audit.
                $refundRequired = true;
                $locked->update([
                    'payment_status' => 'refunded',
                    'admin_notes' => ($locked->admin_notes ? $locked->admin_notes . "\n\n" : '') .
                        "REFUND REQUIRED: Manual gateway refund for booking {$locked->order_id} (reason: {$reason}).",
                ]);

                // Retire any live payment rows for this booking.
                Payment::where('booking_id', $locked->order_id)
                    ->whereIn('status', ['initiated', 'paid'])
                    ->update(['status' => 'refunded']);
            } else {
                $locked->update(['payment_status' => 'failed']);
            }

            $newStatus = match ($cancelledBy) {
                'customer' => 'cancelled_by_customer',
                'admin' => 'cancelled_by_admin',
                'timeout' => 'cancelled',
                default => 'cancelled',
            };

            $locked->update(['status' => $newStatus]);

            // Cancel every service request a partner has not started yet.
            // 'accepted'/'enroute' MUST be included: a partner who accepted a
            // job keeps it until they start work, and leaving those rows alive
            // let them start and complete a booking the customer had already
            // cancelled (which flipped the order back to in_progress).
            // 'working'/'in_progress' are unreachable here - the order-level
            // guard above blocks cancellation once work has started.
            ServiceRequest::where('order_id', $locked->order_id)
                ->whereIn('status', ['pending', 'assigned', 'accepted', 'enroute'])
                ->update(['status' => 'cancelled']);

            Log::info('Booking cancelled', [
                'order_id' => $locked->order_id,
                'user_id' => $locked->user_id,
                'cancelled_by' => $cancelledBy,
                'actor_user_id' => $actorUserId,
                'previous_status' => $locked->getOriginal('status'),
                'new_status' => $newStatus,
                'reason' => $reason,
                'refund_processed' => $wasPaidOnline,
                'refund_required' => $refundRequired,
            ]);

            try {
                AuditService::record($actorUserId, 'booking_cancelled', 'order', $locked->order_id, [
                    'status' => $locked->getOriginal('status'),
                ], [
                    'status' => $newStatus,
                    'payment_status' => $locked->fresh()->payment_status,
                    'reason' => $reason,
                    'cancelled_by' => $cancelledBy,
                    'refund_processed' => $wasPaidOnline,
                    'refund_required' => $refundRequired,
                ]);
            } catch (\Exception $e) {
                Log::warning('Failed to record cancellation audit', ['error' => $e->getMessage()]);
            }

            try {
                NotificationService::sendWithPush(
                    $locked->user_id,
                    'booking_cancelled',
                    'Booking Cancelled',
                    $wasPaidOnline
                        ? "Your booking #{$locked->order_id} was cancelled. A refund of NPR {$locked->total_amount} has been initiated and will be returned to your account."
                        : "Your booking #{$locked->order_id} was cancelled.",
                    ['order_id' => $locked->order_id, 'refund_processed' => $wasPaidOnline]
                );
            } catch (\Exception $e) {
                Log::warning('Failed to notify user about cancellation', ['error' => $e->getMessage()]);
            }

            return [
                'success' => true,
                // Gateway refunds are manual (eSewa/Khalti expose no refund
                // API), so never claim one has been "initiated" - the order is
                // queued for the admin team.
                'message' => $wasPaidOnline
                    ? 'Booking cancelled. Our team will process your refund to the original payment method within 2 working days.'
                    : 'Booking cancelled successfully',
                'refunded' => $wasPaidOnline,
                'refund_required' => $refundRequired,
            ];
        });
    }
}