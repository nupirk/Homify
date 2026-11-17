<?php

namespace App\Services;

use App\Models\CommissionRule;
use App\Models\Service;

class PricingService
{
    /**
     * Format currency amount with proper symbol and decimals
     */
    public static function formatCurrency(float $amount): string
    {
        $symbol = config('pricing.currency.symbol', 'Rs.');
        $decimals = config('pricing.currency.decimal_places', 2);
        return $symbol . ' ' . number_format($amount, $decimals);
    }

    /**
     * Calculate tax amount from base price
     */
    public static function calculateTax(float $basePrice): float
    {
        $taxRate = config('pricing.tax.gst_rate', 0.13);
        return round($basePrice * $taxRate, 2);
    }

    /**
     * Calculate total amount including tax
     */
    public static function calculateTotalWithTax(float $basePrice): float
    {
        return $basePrice + self::calculateTax($basePrice);
    }

    /**
     * Extract base price from total amount (removing tax)
     */
    public static function extractBasePrice(float $totalAmount): float
    {
        $taxRate = config('pricing.tax.gst_rate', 0.13);
        return round($totalAmount / (1 + $taxRate), 2);
    }

    /**
     * Get commission rates for a service category, city, and amount
     */
    public static function getCommissionRates(
        string $serviceCategory,
        string $city,
        float $amount
    ): array {
        // First check if there's a specific commission rule in database
        try {
            $commissionRule = CommissionRule::whereIn('service_id', function ($query) use ($serviceCategory) {
                $query->select('service_id')
                    ->from('services')
                    ->where('name', 'LIKE', "%{$serviceCategory}%")
                    ->orWhere('category_id', function ($subQuery) use ($serviceCategory) {
                        $subQuery->select('category_id')
                            ->from('service_categories')
                            ->where('name', 'LIKE', "%{$serviceCategory}%")
                            ->limit(1);
                    });
            })
                ->where('city', $city)
                ->where('slab_start', '<=', $amount)
                ->where(function ($query) use ($amount) {
                    $query->where('slab_end', '>=', $amount)
                        ->orWhereNull('slab_end');
                })
                ->where(function ($query) {
                    $query->where('valid_from', '<=', now())
                        ->orWhereNull('valid_from');
                })
                ->where(function ($query) {
                    $query->where('valid_to', '>=', now())
                        ->orWhereNull('valid_to');
                })
                ->orderBy('created_at', 'desc')
                ->first();

            if ($commissionRule) {
                return [
                    'platform' => $commissionRule->platform_pct / 100,
                    'partner' => $commissionRule->partner_pct / 100,
                    'source' => 'database_rule',
                    'rule_id' => $commissionRule->rule_id,
                ];
            }
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::warning('Commission rule lookup failed, using fallback rates', [
                'error' => $e->getMessage(),
                'service_category' => $serviceCategory,
                'city' => $city,
                'amount' => $amount
            ]);
        }

        // Fallback to configuration-based rates
        return self::getConfigCommissionRates($serviceCategory, $city, $amount);
    }

    /**
     * Get commission rates from configuration
     */
    private static function getConfigCommissionRates(
        string $serviceCategory,
        string $city,
        float $amount
    ): array {
        $serviceRates = config('pricing.service_commission_rates', []);
        $cityMultipliers = config('pricing.city_commission_multipliers', []);
        $amountSlabs = config('pricing.amount_slabs', []);

        // Get base rates from service category
        $categoryKey = strtolower(str_replace([' ', '-'], ['_', '_'], $serviceCategory));
        $categoryRates = $serviceRates[$categoryKey] ?? $serviceRates['default'];

        // Get city multiplier
        $cityKey = strtolower($city);
        $cityMultiplier = $cityMultipliers[$cityKey] ?? $cityMultipliers['default'];

        // Get amount slab rates
        $amountSlab = null;
        foreach ($amountSlabs as $slab) {
            if ($amount >= $slab['min'] && $amount <= $slab['max']) {
                $amountSlab = $slab;
                break;
            }
        }

        if (!$amountSlab) {
            $amountSlab = end($amountSlabs); // Use highest slab as fallback
        }

        // Calculate final rates (weighted average of category and amount slab)
        $platformRate = ($categoryRates['platform'] * 0.6 + $amountSlab['platform'] * 0.4) * $cityMultiplier;

        // Apply constraints
        $minRate = config('pricing.commission.min_commission_rate', 0.10);
        $maxRate = config('pricing.commission.max_commission_rate', 0.40);

        $platformRate = max($minRate, min($maxRate, $platformRate));
        $partnerRate = 1 - $platformRate;

        return [
            'platform' => round($platformRate, 3),
            'partner' => round($partnerRate, 3),
            'source' => 'config_calculated',
            'rule_id' => null,
        ];
    }

    /**
     * Calculate earnings breakdown for a booking
     */
    public static function calculateEarningsBreakdown(
        float $customerTotalAmount,
        string $serviceCategory,
        string $city
    ): array {
        // Customer total includes tax, so we need to extract base price
        $basePrice = self::extractBasePrice($customerTotalAmount);
        $taxAmount = $customerTotalAmount - $basePrice;

        // Get commission rates
        $rates = self::getCommissionRates($serviceCategory, $city, $basePrice);

        // Calculate platform fee and partner earning from base price (tax-excluded)
        $platformFee = round($basePrice * $rates['platform'], 2);
        $partnerEarning = round($basePrice * $rates['partner'], 2);

        return [
            'customer_total' => $customerTotalAmount,
            'base_price' => $basePrice,
            'tax_amount' => $taxAmount,
            'platform_fee' => $platformFee,
            'partner_earning' => $partnerEarning,
            'commission_rate' => round($rates['platform'] * 100, 1), // Percentage with 1 decimal
            'rates_source' => $rates['source'],
            'commission_rule_id' => $rates['rule_id'],
        ];
    }

    /**
     * Validate earnings breakdown to ensure accuracy
     */
    public static function validateEarningsBreakdown(array $breakdown): bool
    {
        $basePrice = $breakdown['base_price'];
        $taxAmount = $breakdown['tax_amount'];
        $platformFee = $breakdown['platform_fee'];
        $partnerEarning = $breakdown['partner_earning'];
        $customerTotal = $breakdown['customer_total'];

        // Check if customer total equals base price + tax (allow 0.01 tolerance)
        $calculatedTotal = round($basePrice + $taxAmount, 2);
        $totalMatches = abs($calculatedTotal - $customerTotal) < 0.02;

        // Check if base price equals platform fee + partner earning (allow 0.01 tolerance)
        $calculatedBase = round($platformFee + $partnerEarning, 2);
        $baseMatches = abs($calculatedBase - $basePrice) < 0.02;

        return $totalMatches && $baseMatches;
    }

    /**
     * Get standardized commission rate for a service
     */
    public static function getStandardCommissionRate(int $serviceId, string $city, float $amount): array
    {
        $service = Service::with('category')->find($serviceId);

        if (!$service) {
            return self::getConfigCommissionRates('default', $city, $amount);
        }

        $serviceCategory = $service->category?->name ?? 'default';
        return self::getCommissionRates($serviceCategory, $city, $amount);
    }

    /**
     * Calculate partner earning from customer amount
     */
    public static function calculatePartnerEarning(
        float $customerAmount,
        int $serviceId,
        string $city = 'kathmandu'
    ): float {
        $breakdown = self::calculateEarningsBreakdown(
            $customerAmount,
            '', // Will be looked up from service
            $city
        );

        return $breakdown['partner_earning'];
    }
}
