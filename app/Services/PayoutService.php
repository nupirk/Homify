<?php

namespace App\Services;

use App\Models\PartnerPayout;
use App\Services\Payment\EsewaGateway;
use App\Services\Payment\KhaltiGateway;
use Illuminate\Support\Facades\Log;

class PayoutService
{
    /**
     * Initiate payout through specified gateway
     */
    public function initiatePayout($payout, string $gateway, array $options = []): array
    {
        try {
            Log::info('PayoutService: Initiating payout', [
                'payout_id' => $payout->payout_id,
                'gateway' => $gateway,
                'amount' => $payout->net_amount
            ]);

            switch ($gateway) {
                case 'esewa':
                    // eSewa's ePay API only collects money into the platform's
                    // merchant wallet - it cannot send funds to a partner, so
                    // there is no checkout to start. Every eSewa payout is a
                    // manual transfer recorded by an admin.
                    return [
                        'success' => false,
                        'error' => 'eSewa payouts are recorded manually: send the transfer, then enter its eSewa transaction ID in the admin payout form.',
                    ];

                default:
                    return [
                        'success' => false,
                        'error' => 'Unsupported payout gateway: ' . $gateway
                    ];
            }
        } catch (\Exception $e) {
            Log::error('PayoutService: Payout initiation failed', [
                'payout_id' => $payout->payout_id,
                'gateway' => $gateway,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'error' => 'Payout service error: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Verify a payout's status with the gateway.
     *
     * This method never mutates the payout row; the caller decides the next
     * state through LedgerService::finalizePayout / failPayout so that the
     * status machine and ledger stays in one place.
     */
    public function verifyAndUpdatePayout(string $gateway, array $data): array
    {
        try {
            $gatewayInstance = $this->getGatewayInstance($gateway);

            if (!method_exists($gatewayInstance, 'verifyPayout')) {
                return [
                    'success' => false,
                    'error' => "Payout verification not supported for gateway: {$gateway}"
                ];
            }

            $txnId = $data['transaction_uuid'] ?? null;

            if (!$txnId) {
                return [
                    'success' => false,
                    'error' => 'Transaction UUID not provided'
                ];
            }

            // Find payout by transaction reference
            $payout = PartnerPayout::where('transaction_reference', $txnId)->first();

            if (!$payout) {
                return [
                    'success' => false,
                    'error' => 'Payout not found'
                ];
            }

            // Only payouts in flight can be verified; terminal payouts are final.
            if ($payout->status === 'completed') {
                return [
                    'success' => true,
                    'status' => 'completed',
                    'payout' => $payout,
                    'message' => 'Payout already completed'
                ];
            }

            if (!in_array($payout->status, ['pending', 'processing'])) {
                return [
                    'success' => false,
                    'status' => $payout->status,
                    'payout' => $payout,
                    'error' => "Payout is in status: {$payout->status} - cannot verify"
                ];
            }

            // Verify with gateway
            $result = $gatewayInstance->verifyPayout($txnId, $data);

            if ($result['success'] && $result['status'] === 'completed') {
                return [
                    'success' => true,
                    'status' => 'completed',
                    'payout' => $payout,
                    'transaction_id' => $result['transaction_id'],
                    'gateway_data' => $result['gateway_data'] ?? [],
                    'message' => 'Payout verified as completed'
                ];
            }

            return [
                'success' => false,
                'status' => $result['status'] ?? 'failed',
                'payout' => $payout,
                'error' => $result['error'] ?? 'Payout verification failed'
            ];
        } catch (\Exception $e) {
            Log::error('Payout verification error', [
                'gateway' => $gateway,
                'data' => $data,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'error' => 'Payout verification error: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Get gateway instance
     */
    private function getGatewayInstance(string $gateway)
    {
        return match ($gateway) {
            'esewa' => new EsewaGateway('esewa'),
            'khalti' => new KhaltiGateway('khalti'),
            default => throw new \InvalidArgumentException("Unsupported payout gateway: {$gateway}")
        };
    }
}
