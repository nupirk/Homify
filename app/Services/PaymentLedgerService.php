<?php

namespace App\Services;

use App\Models\Order;
use App\Models\ServiceRequest;
use App\Models\LedgerEntry;
use App\Models\PartnerBalance;
use App\Models\CommissionRule;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PaymentLedgerService
{
    /**
     * Create all ledger entries when payment is successful
     */
    public function createPaymentSuccessLedgerEntries(Order $order): void
    {
        try {
            DB::beginTransaction();

            // Check if ledger entries already exist (idempotency)
            if (LedgerEntry::where('booking_id', $order->order_id)->exists()) {
                Log::info('Ledger entries already exist for order', ['order_id' => $order->order_id]);
                DB::rollBack();
                return;
            }

            // Get the service request to find the partner
            $serviceRequest = $order->serviceRequests()->first();
            if (!$serviceRequest) {
                throw new \Exception("No service request found for order {$order->order_id}");
            }

            // Get the service request
            $serviceRequest = $order->serviceRequests()->first();
            if (!$serviceRequest) {
                throw new \Exception("No service request found for order {$order->order_id}");
            }

            // Calculate basic amounts for ledger entries
            $servicePrice = $order->items->sum('total_price');
            $taxAmount = $order->tax_amount ?? 0;
            $platformFee = $order->platform_fee; // This includes tax and any fees

            // Generate detailed service breakdown for narration
            $serviceBreakdown = $order->items->map(function ($item) {
                return "{$item->service->name} x{$item->quantity} (Rs.{$item->total_price})";
            })->implode(', ');

            // Platform user owns the escrow, platform fee and tax rows. The
            // id is configurable (config/payment.php: platform_user_id) - the
            // FK on ledger_entries requires that user row to exist.
            $platformUserId = (int) config('payment.platform_user_id', 1);

            // Create basic ledger entries without partner involvement
            $ledgerEntries = [
                // 1. Customer debit → total amount (money leaves customer)
                [
                    'booking_id' => $order->order_id,
                    'user_id' => $order->user_id,
                    'type' => 'CUSTOMER_DEBIT',
                    'amount' => $order->total_amount,
                    'narration' => "Payment for order {$order->order_id} - Services: {$serviceBreakdown} - Subtotal: Rs.{$servicePrice}, Tax: Rs.{$taxAmount}, Total: Rs.{$order->total_amount}"
                ],

                // 2. Escrow credit → total amount (money held in platform)
                [
                    'booking_id' => $order->order_id,
                    'user_id' => $platformUserId,
                    'type' => 'ESCROW_CREDIT',
                    'amount' => $order->total_amount,
                    'narration' => "Escrow for order {$order->order_id} - Services: {$serviceBreakdown} - Awaiting service completion"
                ],

                // 3. Platform fee credit → platform share
                [
                    'booking_id' => $order->order_id,
                    'user_id' => $platformUserId,
                    'type' => 'PLATFORM_FEE',
                    'amount' => $platformFee,
                    'narration' => "Platform revenue for order {$order->order_id} - Services: {$serviceBreakdown} - Platform fees and tax"
                ]
            ];

            // Add tax entry if there's tax
            if ($taxAmount > 0) {
                $ledgerEntries[] = [
                    'booking_id' => $order->order_id,
                    'user_id' => $platformUserId,
                    'type' => 'TAX_PAYABLE',
                    'amount' => $taxAmount,
                    'narration' => "Tax payable for order {$order->order_id} - Services: {$serviceBreakdown} - 13% VAT (Rs.{$taxAmount})"
                ];
            }

            // Create all ledger entries
            LedgerEntry::createMany($ledgerEntries);

            DB::commit();

            // The promo the platform funded is a cost of its own, not a
            // discount the partner absorbs.
            app(\App\Services\LedgerService::class)->recordDiscountExpense($order);

            Log::info('Payment success ledger entries created', [
                'order_id' => $order->order_id,
                'service_price' => $servicePrice,
                'tax_amount' => $taxAmount,
                'platform_fee' => $platformFee,
                'total_amount' => $order->total_amount
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to create payment success ledger entries', [
                'order_id' => $order->order_id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }

    /**
     * Calculate earnings breakdown for an order
     */
    private function calculateEarningsBreakdown(Order $order, ServiceRequest $serviceRequest): array
    {
        // Get the service price from the order items (excluding fees and taxes)
        $servicePrice = $order->items->sum('total_price'); // This is the actual service price

        // Get commission rule for this service and amount slab
        $serviceId = $order->items->first()?->service_id ?? 1;
        $commissionRule = $this->getApplicableCommissionRule($serviceId, $servicePrice);

        // Calculate partner earnings based on service price only (not total order amount)
        $partnerPercentage = $commissionRule->partner_pct / 100; // Convert 80.00 to 0.8
        $partnerShare = $servicePrice * $partnerPercentage;

        // Platform keeps the remaining service cut + all fees and taxes
        $serviceCut = $servicePrice - $partnerShare;
        $feesAndTaxes = $order->total_amount - $servicePrice; // Platform fee + convenience fee + tax
        $platformShare = $serviceCut + $feesAndTaxes;

        // Extract tax amount
        $taxAmount = $order->tax_amount ?? 0;
        $platformFeeOnly = $platformShare - $taxAmount;

        return [
            'service_price' => $servicePrice,
            'partner_share' => $partnerShare,
            'platform_share' => $platformShare,
            'platform_fee_only' => $platformFeeOnly,
            'tax_amount' => $taxAmount,
            'commission_rate' => $commissionRule->partner_pct
        ];
    }

    /**
     * Get applicable commission rule for service and amount
     */
    private function getApplicableCommissionRule(int $serviceId, float $amount): CommissionRule
    {
        return CommissionRule::where('service_id', $serviceId)
            ->where('min_amount', '<=', $amount)
            ->where(function ($query) use ($amount) {
                $query->where('max_amount', '>=', $amount)
                    ->orWhereNull('max_amount');
            })
            ->orderBy('min_amount', 'desc')
            ->first() ?? CommissionRule::getDefault();
    }

    /**
     * Update partner balance cache when new earnings are created
     */
    private function updatePartnerBalance(int $partnerId, float $earningAmount): void
    {
        try {
            // First, try to find existing balance record
            $partnerBalance = PartnerBalance::where('partner_id', $partnerId)->first();

            if ($partnerBalance) {
                // Update existing balance - PREPAID MODEL
                $partnerBalance->update([
                    'available_balance' => $partnerBalance->available_balance + $earningAmount,
                    'total_earnings' => $partnerBalance->total_earnings + $earningAmount, // Fixed field name
                    'last_updated' => now()
                ]);
            } else {
                // Create new balance record - PREPAID MODEL
                PartnerBalance::create([
                    'partner_id' => $partnerId,
                    'available_balance' => $earningAmount,
                    'total_earnings' => $earningAmount,    // Fixed field name
                    'total_withdrawn' => 0,
                    'last_updated' => now()
                ]);
            }

            Log::info('Partner balance updated successfully', [
                'partner_id' => $partnerId,
                'earning_amount' => $earningAmount
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to update partner balance', [
                'partner_id' => $partnerId,
                'earning_amount' => $earningAmount,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }
}
