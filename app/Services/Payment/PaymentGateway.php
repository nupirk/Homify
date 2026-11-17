<?php

namespace App\Services\Payment;

use App\Models\Payment;
use App\Models\PaymentAttempt;

abstract class PaymentGateway
{
    protected string $gateway;
    protected array $config;

    public function __construct(string $gateway)
    {
        $this->gateway = $gateway;
        $this->config = config("payment.gateways.{$gateway}");
    }

    abstract public function initiatePayment(Payment $payment, array $options = []): array;
    abstract public function verifyPayment(string $txnId, array $data = []): array;
    abstract public function getRedirectUrl(string $gatewayOrderId): string;

    protected function logAttempt(Payment $payment, string $status, array $request = [], array $response = [], string $error = null): PaymentAttempt
    {
        return $payment->attempts()->create([
            'gateway_order_id' => $response['order_id'] ?? null,
            'attempt_status' => $status,
            'request_payload' => $request,
            'response_payload' => $response,
            'error_message' => $error,
        ]);
    }

    protected function generateOrderId(Payment $payment): string
    {
        return "URB-{$payment->booking_id}-{$payment->id}-" . time();
    }
}
