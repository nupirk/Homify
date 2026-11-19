<?php

namespace App\Jobs;

use App\Models\Order;
use App\Models\Payment;
use App\Models\ServiceRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CancelExpiredBookings implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(): void
    {
        Log::info('Starting expired bookings cleanup job');

        // Find expired online orders still awaiting payment. Online orders
        // live in 'created' (before the gateway session) or 'pending_payment'
        // (after initiatePayment) until the money lands - both must be covered.
        // PAS orders are excluded: they are settled at completion and have no
        // payment_expires_at deadline.
        //
        // Rows with a NULL payment_expires_at (legacy orders created before
        // the TTL column was populated, or any writer that forgets to set it)
        // would otherwise be immortal - fall back to order age using the same
        // configured TTL.
        $ttlMinutes = (int) config('payment.payment_timeout_minutes', 15);

        $expiredOrders = Order::where('payment_method', 'online')
            ->whereIn('status', ['created', 'pending_payment'])
            ->where(function ($query) use ($ttlMinutes) {
                $query->where(function ($q) {
                    $q->whereNotNull('payment_expires_at')
                        ->where('payment_expires_at', '<', now());
                })->orWhere(function ($q) use ($ttlMinutes) {
                    $q->whereNull('payment_expires_at')
                        ->where('created_at', '<', now()->subMinutes($ttlMinutes));
                });
            })
            ->with('user')
            ->get();

        $cancelledCount = 0;

        foreach ($expiredOrders as $order) {
            try {
                // Cancellation is collected inside the transaction but the
                // notification and audit writes happen AFTER it commits:
                // push delivery is an outbound HTTP call and auditing is a
                // separate write — neither belongs inside a row lock, where
                // they would hold the lock open for the length of a network
                // round-trip and could deadlock against the webhook path.
                $cancelledOrder = DB::transaction(function () use ($order) {
                    $lockedOrder = Order::whereKey($order->order_id)->lockForUpdate()->first();

                    if (!$lockedOrder) {
                        return null;
                    }
                    if (!in_array($lockedOrder->status, ['created', 'pending_payment'])) {
                        return null;
                    }
                    if ($lockedOrder->payment_status === 'paid' || $lockedOrder->payment_received === 'cash') {
                        return null;
                    }

                    // Mark order as cancelled
                    $lockedOrder->update([
                        'status' => 'cancelled',
                        'payment_status' => 'failed',
                    ]);

                    // Cancel the service requests so no partner can accept,
                    // work or complete an order that was never paid.
                    ServiceRequest::where('order_id', $lockedOrder->order_id)
                        ->whereIn('status', ['pending', 'assigned'])
                        ->update(['status' => 'cancelled']);

                    // Mark associated payments as failed
                    Payment::where('booking_id', $lockedOrder->order_id)
                        ->where('status', 'initiated')
                        ->update([
                            'status' => 'failed',
                            'metadata' => [
                                'failure_reason' => 'Payment expired',
                                'expired_at' => now(),
                            ],
                        ]);

                    // Release the promo redemption: this order was never paid,
                    // so the code must stay usable for the rebooking instead of
                    // burning out on an abandoned payment.
                    \App\Services\PromoService::releaseUsage($lockedOrder);

                    return $lockedOrder->fresh();
                });

                if (!$cancelledOrder) {
                    continue;
                }

                $cancelledCount++;

                Log::info('Cancelled expired booking', [
                    'order_id' => $cancelledOrder->order_id,
                    'user_id' => $cancelledOrder->user_id,
                    'expired_at' => $cancelledOrder->payment_expires_at,
                ]);

                // Notify user about the cancellation (outside the transaction)
                try {
                    \App\Services\NotificationService::send(
                        $cancelledOrder->user_id,
                        'booking_cancelled',
                        'Booking Cancelled',
                        "Your booking #{$cancelledOrder->order_id} was cancelled because the payment expired. Please book again if you still need the service.",
                        ['order_id' => $cancelledOrder->order_id, 'reason' => 'payment_expired']
                    );
                } catch (\Exception $e) {
                    Log::warning('Failed to notify user about expired booking', [
                        'order_id' => $cancelledOrder->order_id,
                        'error' => $e->getMessage()
                    ]);
                }

                try {
                    \App\Services\AuditService::record(null, 'booking_cancelled', 'order', $cancelledOrder->order_id, [
                        'status' => $cancelledOrder->status,
                    ], [
                        'status' => 'cancelled',
                        'reason' => 'payment_expired',
                    ]);
                } catch (\Exception $e) {
                    Log::warning('Failed to audit expired booking cancellation', [
                        'order_id' => $cancelledOrder->order_id,
                        'error' => $e->getMessage()
                    ]);
                }
            } catch (\Exception $e) {
                Log::error('Failed to cancel expired booking', [
                    'order_id' => $order->order_id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        Log::info('Expired bookings cleanup completed', [
            'cancelled_count' => $cancelledCount,
            'total_expired' => $expiredOrders->count(),
        ]);
    }
}