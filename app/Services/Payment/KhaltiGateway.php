<?php

namespace App\Services\Payment;

use App\Models\Payment;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class KhaltiGateway extends PaymentGateway
{
    public function initiatePayment(Payment $payment, array $options = []): array
    {
        $orderId = $this->generateOrderId($payment);

        $payload = [
            'return_url' => $options['return_url'] ?? route('payment.callback', ['gateway' => 'khalti']),
            'website_url' => config('app.url'),
            'amount' => (int)($payment->amount * 100), // Convert to paisa
            'purchase_order_id' => $orderId,
            'purchase_order_name' => $options['product_name'] ?? 'Homify Service Booking',
            'customer_info' => [
                'name' => $options['customer_name'] ?? 'Customer',
                'email' => $options['customer_email'] ?? 'customer@homify.com.np',
                'phone' => $options['customer_phone'] ?? '9800000000',
            ],
        ];

        try {
            $response = Http::withHeaders([
                'Authorization' => 'key ' . $this->config['secret_key'],
                'Content-Type' => 'application/json',
            ])->post($this->config['base_url'] . '/epayment/initiate/', $payload);

            $responseData = $response->json();

            if ($response->successful() && isset($responseData['payment_url'])) {
                $this->logAttempt($payment, 'initiated', $payload, $responseData);

                return [
                    'success' => true,
                    'payment_url' => $responseData['payment_url'],
                    'order_id' => $orderId,
                    'gateway_data' => $responseData,
                ];
            }

            $this->logAttempt($payment, 'failed', $payload, $responseData, 'Failed to initiate payment');

            return [
                'success' => false,
                'error' => $responseData['detail'] ?? 'Payment initiation failed',
                'gateway_data' => $responseData,
            ];
        } catch (\Exception $e) {
            Log::error('Khalti payment initiation failed', [
                'payment_id' => $payment->id,
                'error' => $e->getMessage(),
            ]);

            $this->logAttempt($payment, 'failed', $payload, [], $e->getMessage());

            return [
                'success' => false,
                'error' => 'Payment gateway error: ' . $e->getMessage(),
            ];
        }
    }

    public function verifyPayment(string $txnId, array $data = []): array
    {
        try {
            $response = Http::withHeaders([
                'Authorization' => 'key ' . $this->config['secret_key'],
                'Content-Type' => 'application/json',
            ])->post($this->config['base_url'] . '/epayment/lookup/', [
                'pidx' => $txnId,
            ]);

            $responseData = $response->json();

            if ($response->successful() && $responseData['status'] === 'Completed') {
                return [
                    'success' => true,
                    'status' => 'paid',
                    'transaction_id' => $responseData['transaction_id'],
                    'amount' => $responseData['total_amount'] / 100, // Convert from paisa
                    'gateway_data' => $responseData,
                ];
            }

            return [
                'success' => false,
                'status' => strtolower($responseData['status'] ?? 'failed'),
                'error' => 'Payment verification failed',
                'gateway_data' => $responseData,
            ];
        } catch (\Exception $e) {
            Log::error('Khalti payment verification failed', [
                'txn_id' => $txnId,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => 'Payment verification error: ' . $e->getMessage(),
            ];
        }
    }

    public function getRedirectUrl(string $gatewayOrderId): string
    {
        // Khalti returns direct payment URL, no separate redirect needed
        return '';
    }
}
