<?php

namespace App\Services\Payment;

use App\Models\Payment;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class EsewaGateway extends PaymentGateway
{
    private function getFormUrl(): string
    {
        return rtrim($this->config['form_url'] ?? $this->config['base_url'], '/') . '/api/epay/main/v2/form';
    }

    public function initiatePayment(Payment $payment, array $options = []): array
    {
        $orderId = $this->generateOrderId($payment);

        // eSewa validates the signature against the EXACT total_amount string
        // posted in the form. A DB decimal like "1695.00" cast through float
        // becomes "1695", so signing the float while posting "1695.00"
        // guarantees a signature mismatch on every whole-rupee amount.
        // Normalize ONCE and use the same string in both places.
        $totalAmount = number_format((float) $payment->amount, 2, '.', '');

        $payload = [
            'amount' => $totalAmount,
            'failure_url' => $options['failure_url'] ?? route('payment.failed'),
            'product_delivery_charge' => '0',
            'product_service_charge' => '0',
            'product_code' => $this->config['product_code'],
            'signature' => $this->generateSignature($totalAmount, $orderId),
            'signed_field_names' => 'total_amount,transaction_uuid,product_code',
            'success_url' => $options['success_url'] ?? route('payment.callback', ['gateway' => 'esewa']),
            'tax_amount' => '0',
            'total_amount' => $totalAmount,
            'transaction_uuid' => $orderId,
        ];

        try {
            // eSewa uses form submission, so we return form data
            $this->logAttempt($payment, 'initiated', $payload, []);

            return [
                'success' => true,
                'payment_url' => $this->getFormUrl(),
                'form_data' => $payload,
                'order_id' => $orderId,
                'method' => 'POST',
            ];
        } catch (\Exception $e) {
            Log::error('eSewa payment initiation failed', [
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
            // Build query parameters for GET request
            $queryParams = [
                'product_code' => $this->config['product_code'],
                'total_amount' => $data['total_amount'] ?? 0,
                'transaction_uuid' => $txnId,
            ];

            // Use the status endpoint for the configured environment (UAT vs production)
            $verificationUrl = rtrim($this->config['base_url'], '/') . '/api/epay/transaction/status/?' . http_build_query($queryParams);

            Log::info('eSewa verification request', [
                'url' => $verificationUrl,
                'params' => $queryParams
            ]);

            $response = Http::withHeaders([
                'Content-Type' => 'application/json',
            ])->get($verificationUrl);

            $responseData = $response->json();

            Log::info('eSewa verification response', [
                'status_code' => $response->status(),
                'response' => $responseData
            ]);

            if ($response->successful() && isset($responseData['status']) && $responseData['status'] === 'COMPLETE') {
                $verifiedAmount = (float) ($responseData['total_amount'] ?? 0);
                $expectedAmount = (float) ($data['total_amount'] ?? 0);

                if ($expectedAmount > 0 && abs($verifiedAmount - $expectedAmount) > 0.01) {
                    Log::warning('eSewa amount mismatch during verification', [
                        'txn_id' => $txnId,
                        'verified_amount' => $verifiedAmount,
                        'expected_amount' => $expectedAmount,
                    ]);

                    return [
                        'success' => false,
                        'status' => 'amount_mismatch',
                        'error' => 'Payment amount mismatch',
                        'amount' => $verifiedAmount,
                        'gateway_data' => $responseData,
                    ];
                }

                return [
                    'success' => true,
                    'status' => 'paid',
                    'transaction_id' => $responseData['ref_id'] ?? $txnId,
                    'amount' => $verifiedAmount,
                    'gateway_data' => $responseData,
                ];
            }

            return [
                'success' => false,
                'status' => isset($responseData['status']) ? strtolower($responseData['status']) : 'failed',
                'error' => 'Payment verification failed - Status: ' . ($responseData['status'] ?? 'unknown'),
                'gateway_data' => $responseData,
            ];
        } catch (\Exception $e) {
            Log::error('eSewa payment verification failed', [
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
        return $this->getFormUrl();
    }

    public function generateSignature(string $totalAmount, string $transactionId): string
    {
        $message = "total_amount={$totalAmount},transaction_uuid={$transactionId},product_code=" . $this->config['product_code'];
        $secret = $this->config['secret_key'];

        return base64_encode(hash_hmac('sha256', $message, $secret, true));
    }

    /**
     * eSewa payouts are not supported - refuse instead of faking one.
     *
     * The ePay API (`/api/epay/main/v2/form`) only *collects* money into the
     * platform's own merchant wallet: there is no receiver field and no P2P
     * transfer, so it can never pay a partner. Earlier versions posted a
     * payout to that checkout and stamped the payout `processing` with a
     * generated URB-PAYOUT-... UUID, which no eSewa callback could resolve
     * (the checkout is a payment *into* the platform). Payouts are recorded
     * through the admin manual-transfer flow instead
     * (LedgerController::processManualPayout).
     */
    public function initiatePayout($payout, array $options = []): array
    {
        Log::warning('eSewa payout refused - ePay cannot disburse funds to a partner', [
            'payout_id' => $payout->payout_id ?? null,
        ]);

        return [
            'success' => false,
            'error' => 'eSewa cannot pay a partner automatically. Send the transfer from the platform eSewa account, then record its transaction ID in the admin payout form.',
        ];
    }

    /**
     * Verify eSewa payout status
     */
    public function verifyPayout(string $txnId, array $data = []): array
    {
        try {
            // Build query parameters for payout verification
            $queryParams = [
                'product_code' => $this->config['product_code'],
                'total_amount' => $data['total_amount'] ?? 0,
                'transaction_uuid' => $txnId,
            ];

            // Use the status endpoint for the configured environment (UAT vs production)
            $verificationUrl = rtrim($this->config['base_url'], '/') . '/api/epay/transaction/status/?' . http_build_query($queryParams);

            Log::info('eSewa payout verification request', [
                'url' => $verificationUrl,
                'params' => $queryParams
            ]);

            $response = Http::withHeaders([
                'Content-Type' => 'application/json',
            ])->get($verificationUrl);

            $responseData = $response->json();

            Log::info('eSewa payout verification response', [
                'status_code' => $response->status(),
                'response' => $responseData
            ]);

            if ($response->successful() && isset($responseData['status']) && $responseData['status'] === 'COMPLETE') {
                return [
                    'success' => true,
                    'status' => 'completed',
                    'transaction_id' => $responseData['ref_id'] ?? $txnId,
                    'amount' => $responseData['total_amount'] ?? $data['total_amount'],
                    'gateway_data' => $responseData,
                ];
            }

            return [
                'success' => false,
                'status' => isset($responseData['status']) ? strtolower($responseData['status']) : 'failed',
                'error' => 'Payout verification failed - Status: ' . ($responseData['status'] ?? 'unknown'),
                'gateway_data' => $responseData,
            ];
        } catch (\Exception $e) {
            Log::error('eSewa payout verification failed', [
                'txn_id' => $txnId,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => 'Payout verification error: ' . $e->getMessage(),
            ];
        }
    }

}
