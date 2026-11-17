<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Payment;
use App\Services\PaymentService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class PaymentController extends Controller
{
    protected PaymentService $paymentService;

    public function __construct(PaymentService $paymentService)
    {
        $this->paymentService = $paymentService;
    }

    /**
     * Initiate payment for an order
     */
    public function initiate(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'order_id' => 'required|exists:orders,order_id',
            'gateway' => 'required|in:khalti,esewa',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $order = Order::with(['user', 'items.service'])->findOrFail($request->order_id);

        // Check if user owns this order
        if ($order->user_id !== $request->user()->user_id) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized access to order',
            ], 403);
        }

        // Check if order is in correct status
        if (!in_array($order->status, ['created', 'pending_payment'])) {
            return response()->json([
                'success' => false,
                'message' => 'Order cannot be paid in current status: ' . $order->status,
            ], 400);
        }

        $result = $this->paymentService->initiatePayment(
            $order,
            $request->gateway,
            [
                'return_url' => $request->return_url ?? config('app.frontend_url') . '/payment/callback',
                'failure_url' => $request->failure_url ?? config('app.frontend_url') . '/payment/failed',
                'success_url' => $request->success_url ?? config('app.frontend_url') . '/payment/success',
            ]
        );

        if ($result['success']) {
            return response()->json([
                'success' => true,
                'message' => 'Payment initiated successfully',
                'data' => [
                    'payment_id' => $result['payment_id'],
                    'payment_url' => $result['payment_url'],
                    'gateway' => $request->gateway,
                    'amount' => $order->final_payable,
                    'expires_at' => $order->payment_expires_at,
                    'form_data' => $result['form_data'] ?? null, // For eSewa
                    'method' => $result['method'] ?? 'GET',
                ],
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => $result['error'],
        ], 400);
    }

    /**
     * Retry payment for failed/expired payments
     */
    public function retry(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'payment_id' => 'required|exists:payments,id',
            'gateway' => 'sometimes|in:khalti,esewa',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $payment = Payment::with('booking.user')->findOrFail($request->payment_id);

        // Check if user owns this payment
        if ($payment->booking->user_id !== $request->user()->user_id) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized access to payment',
            ], 403);
        }

        // Check if payment can be retried
        if ($payment->status === 'paid') {
            return response()->json([
                'success' => false,
                'message' => 'Payment already completed',
            ], 400);
        }

        $result = $this->paymentService->retryPayment($payment, $request->gateway);

        if ($result['success']) {
            return response()->json([
                'success' => true,
                'message' => 'Payment retry initiated successfully',
                'data' => [
                    'payment_id' => $result['payment_id'],
                    'payment_url' => $result['payment_url'],
                    'gateway' => $payment->gateway,
                    'amount' => $payment->amount,
                    'form_data' => $result['form_data'] ?? null,
                    'method' => $result['method'] ?? 'GET',
                ],
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => $result['error'],
        ], 400);
    }

    /**
     * Get payment status
     */
    public function status(Request $request, $paymentId): JsonResponse
    {
        $payment = Payment::with('booking.serviceRequests')->findOrFail($paymentId);

        // Check if user owns this payment
        if ($payment->booking->user_id !== $request->user()->user_id) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized access to payment',
            ], 403);
        }

        // If payment is still initiated but expired, mark as failed
        // Compare-and-swap under a row lock: a concurrent webhook that just
        // settled this payment must not be overwritten by the expiry path.
        if ($payment->status === 'initiated' && $payment->isExpired()) {
            Log::info('Marking expired payment as failed', [
                'payment_id' => $payment->id,
                'gateway' => $payment->gateway,
                'expired_at' => $payment->expires_at
            ]);

            DB::transaction(function () use ($payment) {
                $locked = Payment::whereKey($payment->id)->lockForUpdate()->first();

                if ($locked->status !== 'initiated') {
                    return;
                }

                $locked->markAsFailed('Payment expired');

                // Update order status to cancelled
                $locked->booking->update([
                    'status' => 'cancelled',
                    'payment_status' => 'failed'
                ]);

                // Update service requests to cancelled
                foreach ($locked->booking->serviceRequests as $serviceRequest) {
                    $serviceRequest->update(['status' => 'cancelled']);
                }
            });
        }

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $payment->id,
                'status' => $payment->status,
                'amount' => $payment->amount,
                'gateway' => $payment->gateway,
                'txn_id' => $payment->txn_id,
                'expires_at' => $payment->expires_at,
                'is_expired' => $payment->isExpired(),
                'booking_status' => $payment->booking->status,
                'payment_status' => $payment->booking->payment_status,
            ],
        ]);
    }

    /**
     * Cancel a pending payment
     */
    public function cancel(Request $request, $paymentId): JsonResponse
    {
        $payment = Payment::with('booking.serviceRequests')->findOrFail($paymentId);

        // Check if user owns this payment
        if ($payment->booking->user_id !== $request->user()->user_id) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized access to payment',
            ], 403);
        }

        // Only allow cancelling initiated payments
        if ($payment->status !== 'initiated') {
            return response()->json([
                'success' => false,
                'message' => 'Payment cannot be cancelled in current status: ' . $payment->status,
            ], 400);
        }

        Log::info('Cancelling payment by user request', [
            'payment_id' => $payment->id,
            'user_id' => $request->user()->user_id
        ]);

        // Compare-and-swap under a row lock: a concurrent webhook that just
        // settled this payment must not be cancelled afterwards.
        DB::transaction(function () use ($payment) {
            $locked = Payment::whereKey($payment->id)->lockForUpdate()->first();

            if ($locked->status !== 'initiated') {
                return;
            }

            // Mark payment as failed
            $locked->markAsFailed('Payment cancelled by user');

            // Update order status to cancelled
            $locked->booking->update([
                'status' => 'cancelled',
                'payment_status' => 'failed'
            ]);

            // Update service requests to cancelled
            foreach ($locked->booking->serviceRequests as $serviceRequest) {
                $serviceRequest->update(['status' => 'cancelled']);
            }
        });

        return response()->json([
            'success' => true,
            'message' => 'Payment cancelled successfully',
            'data' => [
                'id' => $payment->id,
                'status' => $payment->status,
                'booking_status' => $payment->booking->status,
                'payment_status' => $payment->booking->payment_status,
            ],
        ]);
    }
}
