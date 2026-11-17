<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\PaymentService;
use App\Services\PaymentLedgerService;
use App\Services\MembershipService;
use App\Models\PartnerPayout;
use App\Models\PartnerBalance;
use App\Models\LedgerEntry;
use App\Models\Order;
use App\Models\ServiceRequest;
use App\Support\Redactor;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use GuzzleHttp\Client;

class PaymentWebhookController extends Controller
{
    protected PaymentService $paymentService;

    public function __construct(PaymentService $paymentService)
    {
        $this->paymentService = $paymentService;
    }

    /**
     * Safe log context for a public, attacker-controllable webhook body.
     *
     * Raw payloads are never persisted: signatures, tokens and bank/wallet
     * identifiers are stripped, and a fingerprint lets identical requests be
     * correlated without storing the contents.
     */
    private function safePayload(Request $request): array
    {
        $payload = $request->all();
        $encoded = json_encode($payload) ?: '';

        return [
            'fingerprint' => substr(hash('sha256', $encoded), 0, 16),
            'bytes' => strlen($encoded),
            'keys' => array_keys($payload),
            'data' => Redactor::redactKeys($payload, Redactor::sensitiveKeys()),
        ];
    }

    /**
     * Handle Khalti payment callback/webhook
     */
    public function khalti(Request $request): JsonResponse
    {
        Log::info('Khalti payment callback received', $this->safePayload($request));

        $result = $this->paymentService->verifyAndUpdatePayment('khalti', $request->all());

        if ($result['success']) {
            return response()->json([
                'success' => true,
                'message' => 'Payment verified successfully',
                'order_id' => $result['order']->order_id,
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => $result['error'],
        ], 400);
    }

    /**
     * Handle Khalti payment webhook (server-to-server push from Khalti).
     * Khalti posts the transaction with pidx and purchase_order_id; the
     * transaction is verified against Khalti's lookup API before settling.
     */
    public function handleKhaltiWebhook(Request $request): JsonResponse
    {
        Log::info('Khalti payment webhook received', $this->safePayload($request));

        $payload = $request->all();
        $pidx = $payload['pidx'] ?? null;

        if (!$pidx) {
            return response()->json(['success' => false, 'message' => 'pidx missing'], 422);
        }

        $result = $this->paymentService->verifyAndUpdatePayment('khalti', $payload);

        if ($result['success']) {
            return response()->json([
                'success' => true,
                'message' => 'Payment verified successfully',
                'order_id' => $result['order']->order_id ?? null,
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => $result['error'],
        ], 400);
    }

    /**
     * Handle eSewa payment callback/webhook
     */
    public function esewa(Request $request): JsonResponse
    {
        Log::info('eSewa payment callback received', $this->safePayload($request));

        $result = $this->paymentService->verifyAndUpdatePayment('esewa', $request->all());

        if ($result['success']) {
            return response()->json([
                'success' => true,
                'message' => 'Payment verified successfully',
                'order_id' => $result['order']->order_id,
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => $result['error'],
        ], 400);
    }

    /**
     * Handle eSewa payment webhook (server-to-server push).
     * eSewa posts the same signed payload as the success redirect; verify the
     * signature first, then settle through the status API.
     */
    public function handleEsewaWebhook(Request $request): JsonResponse
    {
        Log::info('eSewa payment webhook received', $this->safePayload($request));

        try {
            $payload = $request->all();

            // eSewa sends base64-encoded signed data when configured with a
            // webhook URL; fall back to raw posted params otherwise.
            if (isset($payload['data'])) {
                $decoded = json_decode(base64_decode($payload['data'], true), true);
                if (is_array($decoded)) {
                    $payload = $decoded;
                }
            }

            $txnUuid = $payload['transaction_uuid'] ?? null;
            if (!$txnUuid) {
                return response()->json(['success' => false, 'message' => 'transaction_uuid missing'], 422);
            }

            $result = $this->paymentService->verifyAndUpdatePayment('esewa', $payload);

            if ($result['success']) {
                return response()->json([
                    'success' => true,
                    'message' => 'Payment verified successfully',
                    'order_id' => $result['order']->order_id ?? null,
                ]);
            }

            return response()->json([
                'success' => false,
                'message' => $result['error'],
            ], 400);
        } catch (\Exception $e) {
            Log::error('eSewa payment webhook processing failed', ['error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => 'Webhook processing failed'], 500);
        }
    }

    /**
     * Generic payment callback handler for mobile app redirects
     */
    public function callback(Request $request, string $gateway): \Illuminate\Http\RedirectResponse
    {
        Log::info("Payment callback received for {$gateway}", $this->safePayload($request));

        $result = $this->paymentService->verifyAndUpdatePayment($gateway, $request->all());

        if ($result['success']) {
            // Redirect to cart screen with success
            return redirect()->away('homify://cart?payment_status=success&order_id=' . $result['order']->order_id);
        }

        // Redirect to cart screen with failure
        $errorMessage = urlencode($result['error']);
        return redirect()->away('homify://cart?payment_status=failed&error=' . $errorMessage);
    }

    /**
     * Handle Khalti payout webhook
     */
    public function khaltiPayout(Request $request): JsonResponse
    {
        try {
            Log::info('Khalti payout webhook received', $this->safePayload($request));

            $payoutId = $request->input('pidx');
            $status = $request->input('status');
            $gatewayPayoutId = $request->input('transaction_id');
            $gatewayFee = $request->input('fee', 0);

            $payout = PartnerPayout::where('transaction_reference', $payoutId)->first();

            if (!$payout) {
                Log::warning('Khalti payout webhook: Payout not found', ['payout_id' => $payoutId]);
                return response()->json(['error' => 'Payout not found'], 404);
            }

            if ($status === 'Completed') {
                $this->completePayoutFromWebhook($payout, $gatewayPayoutId, $gatewayFee, 'khalti');
            } else {
                $this->failPayoutFromWebhook($payout, 'Khalti payout failed: ' . $request->input('message', 'Unknown error'));
            }

            return response()->json(['status' => 'success']);
        } catch (\Exception $e) {
            Log::error('Khalti payout webhook error', [
                'error' => $e->getMessage(),
                'request' => $request->all()
            ]);
            return response()->json(['error' => 'Webhook processing failed'], 500);
        }
    }

    /**
     * eSewa payout webhook.
     *
     * eSewa has no disbursement API, so this endpoint must never finalize a
     * payout. A public payload would otherwise only have to match the
     * transaction reference an admin typed in by hand, and anyone who guessed
     * that reference could complete - or fail - a real payout. The callback is
     * recorded for an admin to confirm against the actual transfer instead.
     */
    public function esewaPayout(Request $request): JsonResponse
    {
        try {
            Log::info('eSewa payout webhook received', $this->safePayload($request));

            $payoutId = $request->input('transaction_uuid');

            $payout = $payoutId
                ? PartnerPayout::where('transaction_reference', $payoutId)
                    ->whereIn('status', ['pending', 'processing'])
                    ->first()
                : null;

            if ($payout) {
                $payout->update([
                    'metadata' => array_merge($payout->metadata ?? [], [
                        'esewa_webhook_status' => $request->input('status'),
                        'esewa_webhook_ref' => $request->input('ref_id'),
                        'esewa_webhook_received_at' => now()->toISOString(),
                    ]),
                ]);
            } else {
                Log::warning('eSewa payout webhook: no in-flight payout matched', [
                    'transaction_uuid' => $payoutId,
                ]);
            }

            return response()->json([
                'status' => 'recorded',
                'message' => 'eSewa payout callbacks are recorded only; an admin confirms the transfer.',
            ]);
        } catch (\Exception $e) {
            Log::error('eSewa payout webhook error', [
                'error' => $e->getMessage(),
                'request' => $request->all()
            ]);
            return response()->json(['error' => 'Webhook processing failed'], 500);
        }
    }

    /**
     * Complete payout from webhook
     */
    private function completePayoutFromWebhook($payout, $gatewayPayoutId, $gatewayFee, $gateway)
    {
        try {
            // Idempotent + race-safe finalization via the shared LedgerService:
            // status re-verification happens inside its own transaction and the
            // withdrawal ledger entry is recorded exactly once.
            app(\App\Services\LedgerService::class)->finalizePayout(
                $payout->payout_id,
                [
                    'gateway_payout_id' => $gatewayPayoutId,
                    'final_gateway_fee' => $gatewayFee,
                    'source' => 'webhook',
                    'metadata' => [
                        'completed_via_webhook' => true,
                        'webhook_gateway' => $gateway,
                    ],
                ]
            );

            Log::info('Payout completed via webhook', [
                'payout_id' => $payout->payout_id,
                'partner_id' => $payout->partner_id,
                'gateway' => $gateway,
                'gateway_payout_id' => $gatewayPayoutId
            ]);
        } catch (\Exception $e) {
            Log::error('Payout webhook completion failed', [
                'payout_id' => $payout->payout_id,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Fail payout from webhook
     */
    private function failPayoutFromWebhook($payout, $failureReason)
    {
        try {
            // Shared failure path: restores the balance exactly once, and only
            // if the money was actually withdrawn (manual payouts).
            app(\App\Services\LedgerService::class)->failPayout(
                $payout->payout_id,
                $failureReason,
                'gateway_webhook'
            );

            Log::warning('Payout failed via webhook', [
                'payout_id' => $payout->payout_id,
                'partner_id' => $payout->partner_id,
                'failure_reason' => $failureReason
            ]);
        } catch (\Exception $e) {
            Log::error('Payout webhook failure handling failed', [
                'payout_id' => $payout->payout_id,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Handle eSewa payment success callback
     */
    public function esewaSuccess(Request $request): \Illuminate\Http\RedirectResponse
    {
        try {
            Log::info('eSewa success callback received', $this->safePayload($request));

            // Decode the eSewa response data
            $data = json_decode(base64_decode($request->data), true);

            // First verify signature using the correct message format
            $message = "transaction_code={$data['transaction_code']},status={$data['status']},total_amount={$data['total_amount']},transaction_uuid={$data['transaction_uuid']},product_code={$data['product_code']},signed_field_names={$data['signed_field_names']}";
            $sig = base64_encode(
                hash_hmac(
                    'sha256',
                    $message,
                    config('payment.gateways.esewa.secret_key', ''),
                    true
                )
            );

            if ($sig !== $data['signature']) {
                Log::error('eSewa signature verification failed', [
                    'received_signature' => $data['signature'],
                    'calculated_signature' => $sig,
                    'message' => $message
                ]);
                return redirect()->away('homify://cart?payment_status=failed&error=' . urlencode('Invalid signature'));
            }

            Log::info('eSewa signature verified successfully');

            // Process payment success with PaymentService (which will call the status API)
            $result = $this->paymentService->verifyAndUpdatePayment('esewa', $data);

            if ($result['success']) {
                // Redirect to app cart screen with success
                return redirect()->away('homify://cart?payment_status=success&order_id=' . $result['order']->order_id);
            }

            // Redirect to app cart screen with failure
            return redirect()->away('homify://cart?payment_status=failed&error=' . urlencode($result['error']));
        } catch (\Exception $e) {
            Log::error('eSewa success callback failed', ['error' => $e->getMessage()]);
            return redirect()->away('homify://cart?payment_status=failed&error=' . urlencode('Payment processing failed'));
        }
    }

    /**
     * Handle eSewa payment failure callback
     */
    public function esewaFailure(Request $request): \Illuminate\Http\RedirectResponse
    {
        try {
            Log::info('eSewa failure callback received', array_merge(
                $this->safePayload($request),
                [
                    'method' => $request->method(),
                    'url' => $request->fullUrl(),
                ]
            ));

            // Try to find and update order if transaction_uuid is available
            if ($request->has('transaction_uuid')) {
                $this->handlePaymentFailure($request->transaction_uuid, 'Payment cancelled by user');
            }

            return redirect()->away('homify://cart?payment_status=failed&error=' . urlencode('Payment cancelled or failed'));
        } catch (\Exception $e) {
            Log::error('eSewa failure callback processing failed', ['error' => $e->getMessage()]);
            return redirect()->away('homify://cart?payment_status=failed&error=' . urlencode('Payment processing failed'));
        }
    }

    /**
     * Handle eSewa success callback for a membership purchase.
     * Same signed-envelope scheme as esewaSuccess, but the entitlement row
     * lives in user_memberships (no order/payment involved).
     */
    public function membershipEsewaSuccess(Request $request): \Illuminate\Http\RedirectResponse
    {
        $redirect = fn (string $status, string $error = '') => redirect()->away(
            'homify://profile/membership?payment_status=' . $status
            . ($error !== '' ? '&error=' . urlencode($error) : '')
        );

        try {
            Log::info('eSewa membership success callback received', $this->safePayload($request));

            $data = json_decode(base64_decode($request->data), true);

            if (!is_array($data) || !isset($data['signature'], $data['transaction_uuid'])) {
                return $redirect('failed', 'Invalid callback payload');
            }

            // Verify the signature over the exact field order eSewa signs.
            $message = "transaction_code={$data['transaction_code']},status={$data['status']},total_amount={$data['total_amount']},transaction_uuid={$data['transaction_uuid']},product_code={$data['product_code']},signed_field_names={$data['signed_field_names']}";
            $sig = base64_encode(
                hash_hmac('sha256', $message, config('payment.gateways.esewa.secret_key', ''), true)
            );

            if (!hash_equals((string) $sig, (string) $data['signature'])) {
                Log::error('eSewa membership signature verification failed', [
                    'transaction_uuid' => $data['transaction_uuid'],
                ]);
                return $redirect('failed', 'Invalid signature');
            }

            $result = MembershipService::verifyAndActivate($data);

            if ($result['success']) {
                return $redirect('success');
            }

            return $redirect('failed', $result['error'] ?? 'Payment failed');
        } catch (\Exception $e) {
            Log::error('eSewa membership success callback failed', ['error' => $e->getMessage()]);
            return $redirect('failed', 'Payment processing failed');
        }
    }

    /**
     * Handle eSewa failure/cancel callback for a membership purchase.
     */
    public function membershipEsewaFailure(Request $request): \Illuminate\Http\RedirectResponse
    {
        try {
            Log::info('eSewa membership failure callback received', $this->safePayload($request));

            if ($request->has('transaction_uuid')) {
                MembershipService::markFailed((string) $request->transaction_uuid);
            }

            return redirect()->away('homify://profile/membership?payment_status=failed&error=' . urlencode('Payment cancelled or failed'));
        } catch (\Exception $e) {
            Log::error('eSewa membership failure callback failed', ['error' => $e->getMessage()]);
            return redirect()->away('homify://profile/membership?payment_status=failed&error=' . urlencode('Payment processing failed'));
        }
    }

    /**
     * Handle payment failure by updating order and service request status
     */
    private function handlePaymentFailure(string $transactionUuid, string $reason): void
    {
        try {
            DB::beginTransaction();

            // Find order by transaction UUID
            $order = Order::where('transaction_uuid', $transactionUuid)->first();

            if ($order) {
                // Idempotency guard: only cancel a booking that is still
                // awaiting payment. A failure callback arriving after the
                // gateway money landed, after a cash settlement, or after the
                // job was completed must NEVER cancel a paid/completed order.
                if ($order->payment_status === 'paid' || $order->status === 'completed' || $order->payment_received === 'cash') {
                    Log::warning('Ignoring payment failure - order is already settled', [
                        'order_id' => $order->order_id,
                        'transaction_uuid' => $transactionUuid,
                        'payment_status' => $order->payment_status,
                        'order_status' => $order->status,
                        'reason' => $reason,
                    ]);
                } else {
                    // Update order status to failed
                    $order->update([
                        'payment_status' => 'failed',
                        'status' => 'cancelled',
                    ]);

                    // Cancel every request on the order, not just the first:
                    // multi-service orders used to retain live requests after
                    // a failed payment.
                    $cancelledRequests = ServiceRequest::where('order_id', $order->order_id)
                        ->whereNotIn('status', ['completed', 'in_progress', 'working'])
                        ->update(['status' => 'cancelled']);

                    // Retire the gateway attempt so it cannot be mistaken for
                    // a still-open session by the expiry job or a retry.
                    \App\Models\Payment::where('booking_id', $order->order_id)
                        ->whereIn('status', ['initiated', 'pending'])
                        ->update([
                            'status' => 'failed',
                            'metadata' => [
                                'failure_reason' => $reason,
                                'failed_at' => now(),
                            ],
                        ]);

                    Log::info('Service requests cancelled due to payment failure', [
                        'order_id' => $order->order_id,
                        'cancelled_count' => $cancelledRequests,
                        'reason' => $reason
                    ]);

                    Log::info('Order cancelled due to payment failure', [
                        'order_id' => $order->order_id,
                        'transaction_uuid' => $transactionUuid,
                        'reason' => $reason
                    ]);
                }
            }

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to handle payment failure', [
                'transaction_uuid' => $transactionUuid,
                'reason' => $reason,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Handle payment timeout - called when payments expire
     */
    public function handlePaymentTimeout($orderId): JsonResponse
    {
        try {
            $order = Order::where('order_id', $orderId)->first();

            if ($order && $order->payment_status === 'pending') {
                $this->handlePaymentFailure($order->transaction_uuid, 'Payment timeout');

                return response()->json([
                    'success' => true,
                    'message' => 'Order cancelled due to payment timeout'
                ]);
            }

            return response()->json([
                'success' => false,
                'message' => 'Order not found or not in pending state'
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to handle payment timeout'
            ], 500);
        }
    }
}
