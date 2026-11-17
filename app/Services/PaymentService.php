<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Payment;
use App\Services\Payment\KhaltiGateway;
use App\Services\Payment\EsewaGateway;
use App\Services\PaymentLedgerService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PaymentService
{
    protected PaymentLedgerService $paymentLedgerService;

    public function __construct(PaymentLedgerService $paymentLedgerService)
    {
        $this->paymentLedgerService = $paymentLedgerService;
    }
    public function initiatePayment(Order $order, string $gateway, array $options = []): array
    {
        try {
            // Calculate final payable amount
            $finalAmount = $this->calculateFinalAmount($order);

            $ttlMinutes = (int) config('payment.payment_timeout_minutes', 15);

            // Create payment record
            $payment = Payment::create([
                'booking_id' => $order->order_id,
                'gateway' => $gateway,
                'amount' => $finalAmount,
                'status' => 'initiated',
                'expires_at' => now()->addMinutes($ttlMinutes),
            ]);

            // Update order with payment details
            $order->update([
                'status' => 'pending_payment',
                'payment_status' => 'pending',
                'final_payable' => $finalAmount,
                'payment_expires_at' => now()->addMinutes($ttlMinutes),
            ]);

            // Get gateway instance
            $gatewayInstance = $this->getGatewayInstance($gateway);

            // Initiate payment with gateway
            $result = $gatewayInstance->initiatePayment($payment, array_merge($options, [
                'customer_name' => $order->user->first_name . ' ' . $order->user->last_name,
                'customer_email' => $order->user->email,
                'customer_phone' => $order->user->phone,
                'product_name' => 'Homify Service - ' . $order->items->first()?->service->name,
            ]));

            if (!$result['success']) {
                $payment->markAsFailed($result['error']);
                $order->update(['status' => 'created']); // Revert status
            } else {
                // The gateway's transaction uuid (order id) is the key the
                // callback/webhook echoes back. Store it as txn_id so
                // verifyAndUpdatePayment can find this row again.
                $gatewayOrderId = $result['order_id'] ?? null;
                if ($gatewayOrderId) {
                    $payment->update([
                        'txn_id' => $gatewayOrderId,
                        'metadata' => array_merge($payment->metadata ?? [], [
                            'gateway_order_id' => $gatewayOrderId,
                        ]),
                    ]);
                }
            }

            return array_merge($result, ['payment_id' => $payment->id]);
        } catch (\Exception $e) {
            Log::error('Payment initiation failed', [
                'order_id' => $order->order_id,
                'gateway' => $gateway,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => 'Payment initiation failed: ' . $e->getMessage(),
            ];
        }
    }

    public function verifyAndUpdatePayment(string $gateway, array $data): array
    {
        try {
            $gatewayInstance = $this->getGatewayInstance($gateway);

            // Khalti echoes both pidx and purchase_order_id; eSewa echoes
            // transaction_uuid. purchase_order_id/transaction_uuid is OUR
            // generated id (stored as payment.txn_id at initiation), while
            // pidx is Khalti's own index - so match ours first, pidx second.
            $txnId = $data['transaction_uuid'] ?? $data['purchase_order_id'] ?? $data['pidx'] ?? null;

            if (!$txnId) {
                return ['success' => false, 'error' => 'Transaction ID missing'];
            }

            // Khalti's lookup API is keyed by pidx (its own index), not by our
            // purchase order id - verify with pidx when it is present.
            $verifyId = ($gateway === 'khalti' && isset($data['pidx'])) ? $data['pidx'] : $txnId;

            // Find payment by transaction UUID
            $payment = Payment::where('gateway', $gateway)
                ->where('txn_id', $txnId)
                ->latest('id')
                ->first();

            // Khalti webhooks only carry pidx; find the payment via the stored
            // pidx metadata from a previous lookup, or via the gateway order id.
            if (!$payment && $gateway === 'khalti' && isset($data['pidx'])) {
                $payment = Payment::where('gateway', 'khalti')
                    ->where('metadata->pidx', $data['pidx'])
                    ->first();
            }

            if (!$payment) {
                return ['success' => false, 'error' => 'Payment not found'];
            }

            // Idempotency: a verification for an already-paid payment is a
            // duplicate callback (gateway retries, redirect + webhook double
            // fires). Acknowledge it without re-processing - no duplicate
            // notifications, audits or ledger work.
            if ($payment->status === 'paid') {
                return [
                    'success' => true,
                    'already_paid' => true,
                    'payment' => $payment,
                    'order' => $payment->booking,
                ];
            }

            // Verify with gateway
            $verification = $gatewayInstance->verifyPayment($verifyId, $data);

            if ($verification['success'] && $verification['status'] === 'paid') {
                // Amount guard: never settle a payment whose verified amount
                // differs from what we charged. Under/over-payment is an
                // anomaly for admin review, not a success.
                $verifiedAmount = (float) ($verification['amount'] ?? 0);
                $expectedAmount = (float) $payment->amount;
                if ($verifiedAmount > 0 && abs($verifiedAmount - $expectedAmount) > 0.01) {
                    Log::critical('Payment amount mismatch - refusing to settle', [
                        'order_id' => $payment->booking_id,
                        'payment_id' => $payment->id,
                        'gateway' => $gateway,
                        'verified_amount' => $verifiedAmount,
                        'expected_amount' => $expectedAmount,
                        'txn_id' => $txnId,
                    ]);

                    // Flag the row so the payments:held command (and admins)
                    // can surface it for manual review.
                    $payment->update([
                        'metadata' => array_merge($payment->metadata ?? [], [
                            'held_for_review' => true,
                            'amount_mismatch' => true,
                            'mismatch_reported_at' => now(),
                        ]),
                    ]);

                    try {
                        AuditService::record($payment->booking->user_id, 'payment_amount_mismatch', 'order', $payment->booking_id, [
                            'payment_status' => $payment->status,
                        ], [
                            'verified_amount' => $verifiedAmount,
                            'expected_amount' => $expectedAmount,
                            'gateway' => $gateway,
                            'txn_id' => $txnId,
                        ]);
                    } catch (\Exception $e) {
                        Log::warning('Failed to record payment_amount_mismatch audit', ['error' => $e->getMessage()]);
                    }

                    return [
                        'success' => false,
                        'error' => 'Payment amount mismatch',
                        'payment' => $payment,
                        'order' => $payment->booking,
                    ];
                }

                // Compare-and-swap: re-check the payment state under a row lock
                // and settle only if it is still initiated. A concurrent
                // webhook/redirect that already settled this payment must not
                // be double-processed (double ledger entries, double orders).
                return DB::transaction(function () use ($payment, $verification, $txnId, $gateway, $data) {
                    $lockedPayment = Payment::whereKey($payment->id)->lockForUpdate()->first();

                    if ($lockedPayment->status === 'paid') {
                        return [
                            'success' => true,
                            'already_paid' => true,
                            'payment' => $lockedPayment,
                            'order' => $lockedPayment->booking,
                        ];
                    }

                    // Late-payment guard: if this booking was settled in cash at the door
                    // (PAS cash settlement), the gateway must NOT also take the money.
                    // Auto-refund and leave the order untouched.
                    $booking = $lockedPayment->booking;
                    if ($booking->payment_received === 'cash' || $booking->cash_confirmed) {
                        Log::warning('Gateway payment received after cash settlement - auto-refunding', [
                            'order_id' => $booking->order_id,
                            'payment_id' => $lockedPayment->id,
                            'txn_id' => $txnId,
                        ]);

                        $lockedPayment->update(['status' => 'refunded']);

                        try {
                            NotificationService::sendWithPush(
                                $booking->user_id,
                                'payment_refunded',
                                'Payment refunded',
                                "You paid NPR {$booking->total_amount} online for booking #{$booking->order_id}, but that booking was already settled in cash. Your online payment has been refunded.",
                                ['order_id' => $booking->order_id, 'amount' => $booking->total_amount]
                            );
                        } catch (\Exception $e) {
                            Log::warning('Failed to notify customer of late-payment refund', ['error' => $e->getMessage()]);
                        }

                        try {
                            AuditService::record($booking->user_id, 'payment_auto_refunded', 'order', $booking->order_id, [
                                'payment_status' => 'paid',
                                'payment_received' => 'cash',
                            ], [
                                'refund_reason' => 'Gateway payment arrived after cash settlement',
                                'amount' => $booking->total_amount,
                                'gateway' => $gateway,
                            ]);
                        } catch (\Exception $e) {
                            Log::warning('Failed to record payment_auto_refunded audit', ['error' => $e->getMessage()]);
                        }

                        return [
                            'success' => true,
                            'refunded' => true,
                            'payment' => $lockedPayment,
                            'order' => $booking,
                        ];
                    }

                    // Remember the khalti pidx for future webhook matching
                    $metadataPatch = [];
                    if ($gateway === 'khalti' && isset($data['pidx'])) {
                        $metadataPatch['pidx'] = $data['pidx'];
                    }

                    // Mark payment as paid
                    $lockedPayment->markAsPaid($verification['transaction_id'], array_merge(
                        $verification['gateway_data'] ?? [],
                        $metadataPatch
                    ));

                    // Update order status. A booking with a preferred partner
                    // stayed 'pending' while unpaid (it must never look booked
                    // before the money lands); only now every request is
                    // promoted to 'assigned' and the partner gets woken up.
                    $order = $lockedPayment->booking;

                    // A completed job must never be demoted back to
                    // 'confirmed'/'partner_assigned' by a late settlement
                    // (e.g. PAS paid online after completion).
                    $isCompleted = $order->status === 'completed';

                    // Same rule for a job already underway: in the
                    // pay-after-service flow the customer can settle online
                    // while the partner is accepted/en route/working. Paying
                    // must not pull the booking back to 'confirmed' - that hid
                    // the fact that work had already started.
                    $inFlightStatus = in_array($order->status, [
                        'accepted',
                        'enroute',
                        'in_progress',
                        'working',
                    ], true)
                        ? $order->status
                        : null;

                    $preferredRequests = $isCompleted
                        ? collect()
                        : $order->serviceRequests()
                            ->whereNotNull('partner_id')
                            ->whereIn('status', ['pending', 'assigned', 'cancelled'])
                            ->get();

                    if ($preferredRequests->isNotEmpty()) {
                        foreach ($preferredRequests as $request) {
                            $request->update([
                                'status' => 'assigned',
                                'partner_assigned_at' => now(),
                                // The acceptance clock starts now, not while
                                // the customer was in the gateway.
                                'assignment_expires_at' => now()->addMinutes(5),
                                'assignment_timeout_minutes' => 5,
                                'assignment_method' => $request->assignment_method ?: 'customer_selected',
                            ]);
                        }

                        $order->update([
                            'status' => $isCompleted
                                ? 'completed'
                                : ($inFlightStatus ?? 'partner_assigned'),
                            'payment_status' => 'paid',
                        ]);

                        try {
                            $narration = $order->items->map(function ($item) {
                                return "{$item->service->name} x{$item->quantity}";
                            })->implode(', ');

                            // One push per partner, even for multi-service orders.
                            foreach ($preferredRequests->pluck('partner_id')->unique() as $partnerId) {
                                NotificationService::sendWithPush(
                                    $partnerId,
                                    'new_job',
                                    'New Job Available',
                                    "You have been selected for a new job (#{$order->order_id}): {$narration}. Please accept it within 5 minutes.",
                                    ['order_id' => $order->order_id, 'services' => $narration]
                                );
                            }
                        } catch (\Exception $e) {
                            Log::warning('Failed to notify assigned partner after payment', ['error' => $e->getMessage()]);
                        }
                    } else {
                        if (!$isCompleted) {
                            // No preferred partner: auto-assign will pick these
                            // up after payment. Restore any request cancelled
                            // by a failure callback so a late success still
                            // books it.
                            $order->serviceRequests()
                                ->whereIn('status', ['cancelled'])
                                ->update(['status' => 'pending']);
                        }

                        $order->update([
                            'status' => $isCompleted
                                ? 'completed'
                                : ($inFlightStatus ?? 'confirmed'),
                            'payment_status' => 'paid',
                        ]);
                    }

                    // Note: service request status is intentionally NOT updated here.
                    // It is created as 'pending'/'assigned' at booking and progresses via
                    // partner acceptance — 'confirmed' is not a valid service_request status.

                    // Create ledger entries for successful payment
                    try {
                        $this->paymentLedgerService->createPaymentSuccessLedgerEntries($lockedPayment->booking);
                        Log::info('Ledger entries created for successful payment', [
                            'order_id' => $lockedPayment->booking->order_id,
                            'payment_id' => $lockedPayment->id
                        ]);
                    } catch (\Exception $e) {
                        Log::error('Failed to create ledger entries for payment success', [
                            'order_id' => $lockedPayment->booking->order_id,
                            'payment_id' => $lockedPayment->id,
                            'error' => $e->getMessage()
                        ]);
                        // Note: We don't fail the payment if ledger creation fails
                        // The payment is successful, but we log the ledger error
                    }

                    // Notify customer about successful payment
                    try {
                        NotificationService::sendWithPush(
                            $lockedPayment->booking->user_id,
                            'payment_received',
                            'Payment Successful',
                            "Your payment of NPR {$lockedPayment->booking->total_amount} for booking #{$lockedPayment->booking->order_id} was successful.",
                            ['order_id' => $lockedPayment->booking->order_id, 'amount' => $lockedPayment->booking->total_amount]
                        );
                    } catch (\Exception $e) {
                        Log::warning('Failed to notify customer of payment success', ['error' => $e->getMessage()]);
                    }

                    AuditService::record($lockedPayment->booking->user_id, 'payment_success', 'order', $lockedPayment->booking->order_id, [
                        'payment_status' => 'pending',
                    ], [
                        'payment_status' => 'paid',
                        'amount' => $lockedPayment->booking->total_amount,
                        'gateway' => $gateway,
                    ]);

                    return [
                        'success' => true,
                        'payment' => $lockedPayment,
                        'order' => $lockedPayment->booking,
                    ];
                });
            }

            // Payment failed or incomplete. Same CAS discipline as the success
            // path: re-check the payment state under a row lock so a stale
            // failure callback can never cancel a payment that a concurrent
            // success callback just settled.
            return DB::transaction(function () use ($payment, $verification, $txnId, $gateway) {
                $lockedPayment = Payment::whereKey($payment->id)->lockForUpdate()->first();

                // Idempotency: ignore a stale failure callback if the payment has
                // since been marked paid (gateway retries can arrive out of order).
                if ($lockedPayment->status === 'paid') {
                    Log::warning('Ignoring late payment failure - payment is already paid', [
                        'order_id' => $lockedPayment->booking->order_id,
                        'payment_id' => $lockedPayment->id,
                        'txn_id' => $txnId,
                        'verification_error' => $verification['error'] ?? 'Payment verification failed',
                    ]);

                    return [
                        'success' => true,
                        'already_paid' => true,
                        'payment' => $lockedPayment,
                        'order' => $lockedPayment->booking,
                    ];
                }

                // Gateway-detected amount mismatch: the amount echoed in the
                // callback does not match the amount the gateway confirmed.
                // This is an anomaly for admin review, NOT a plain failure -
                // money may have moved at a different amount, so the payment
                // must stay initiated (never marked failed, never cancelled)
                // until a human decides.
                if (($verification['status'] ?? null) === 'amount_mismatch') {
                    Log::critical('Gateway reported amount mismatch - payment held for review', [
                        'order_id' => $lockedPayment->booking_id,
                        'payment_id' => $lockedPayment->id,
                        'txn_id' => $txnId,
                        'gateway' => $gateway,
                        'error' => $verification['error'] ?? 'Payment amount mismatch',
                    ]);

                    // Flag the row so the payments:held command (and admins)
                    // can surface it for manual review.
                    $lockedPayment->update([
                        'metadata' => array_merge($lockedPayment->metadata ?? [], [
                            'held_for_review' => true,
                            'amount_mismatch' => true,
                            'mismatch_reported_at' => now(),
                        ]),
                    ]);

                    try {
                        AuditService::record($lockedPayment->booking->user_id, 'payment_amount_mismatch', 'order', $lockedPayment->booking_id, [
                            'payment_status' => $lockedPayment->status,
                        ], [
                            'gateway' => $gateway,
                            'txn_id' => $txnId,
                            'error' => $verification['error'] ?? 'Payment amount mismatch',
                            'held_for_review' => true,
                        ]);
                    } catch (\Exception $e) {
                        Log::warning('Failed to record payment_amount_mismatch audit', ['error' => $e->getMessage()]);
                    }

                    return [
                        'success' => false,
                        'error' => $verification['error'] ?? 'Payment amount mismatch',
                        'payment' => $lockedPayment,
                        'order' => $lockedPayment->booking,
                    ];
                }

                $lockedPayment->markAsFailed($verification['error'] ?? 'Payment verification failed');

                // Update order status to failed
                $lockedPayment->booking->update([
                    'payment_status' => 'failed',
                    'status' => 'cancelled',
                ]);

                // Cancel every request on the order - a multi-service
                // booking must not keep one live request after a failure.
                $cancelledRequests = \App\Models\ServiceRequest::where('order_id', $lockedPayment->booking->order_id)
                    ->whereNotIn('status', ['completed', 'in_progress', 'working'])
                    ->update(['status' => 'cancelled']);

                Log::info('Service requests cancelled due to payment failure', [
                    'order_id' => $lockedPayment->booking->order_id,
                    'cancelled_count' => $cancelledRequests,
                ]);

                try {
                    AuditService::record($lockedPayment->booking->user_id, 'payment_failed', 'order', $lockedPayment->booking->order_id, [
                        'payment_status' => 'pending',
                    ], [
                        'payment_status' => 'failed',
                        'gateway' => $gateway,
                        'reason' => $verification['error'] ?? 'Payment verification failed',
                    ]);
                } catch (\Exception $e) {
                    Log::warning('Failed to record payment_failed audit', ['error' => $e->getMessage()]);
                }

                return [
                    'success' => false,
                    'error' => $verification['error'] ?? 'Payment verification failed',
                    'payment' => $lockedPayment,
                ];
            });
        } catch (\Exception $e) {
            Log::error('Payment verification failed', [
                'gateway' => $gateway,
                'data' => $data,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => 'Payment verification error: ' . $e->getMessage(),
            ];
        }
    }

    public function calculateFinalAmount(Order $order): float
    {
        // Unified pricing: the booking quote (subtotal + GST via PricingService in
        // BookingController::store) is the single source of truth.
        // No hidden fees (service charge / platform fee / tax-on-fees) are added at payment time.
        $finalAmount = $order->final_payable ?? $order->total_amount ?? 0;

        return max($finalAmount, 0); // Ensure non-negative
    }

    private function getGatewayInstance(string $gateway): KhaltiGateway|EsewaGateway
    {
        return match ($gateway) {
            'khalti' => new KhaltiGateway($gateway),
            'esewa' => new EsewaGateway($gateway),
            default => throw new \InvalidArgumentException("Unsupported gateway: {$gateway}")
        };
    }

    public function retryPayment(Payment $payment, string $gateway = null): array
    {
        $gateway = $gateway ?? $payment->gateway;

        // CAS: refuse to retry a payment that was settled concurrently.
        return DB::transaction(function () use ($payment, $gateway) {
            $lockedPayment = Payment::whereKey($payment->id)->lockForUpdate()->first();

            if ($lockedPayment->status === 'paid') {
                return ['success' => false, 'error' => 'Payment already completed'];
            }

            if ($lockedPayment->status === 'initiated' && !$lockedPayment->isExpired()) {
                // Reuse the live initiated session instead of stacking a new one.
                return ['success' => false, 'error' => 'Payment already initiated'];
            }

            // Retire this row before a fresh session is created - a live
            // 'initiated' row would otherwise race with payments:expire.
            $lockedPayment->update([
                'status' => 'failed',
                'metadata' => array_merge($lockedPayment->metadata ?? [], [
                    'failure_reason' => 'Superseded by payment retry',
                    'superseded_at' => now(),
                ]),
            ]);

            return $this->initiatePayment($lockedPayment->booking, $gateway);
        });
    }
}
