<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Promotion;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Promo code validation, discount math and usage recording.
 *
 * Money rule: discounts are platform-funded and always apply to the
 * GROSS subtotal BEFORE tax. Orders keep subtotal gross, record the
 * cut in discount_amount, and charge (subtotal - discount) + tax.
 */
class PromoService
{
    /**
     * Look up a promotion by code (case-insensitive, trimmed).
     */
    public static function findPromotion(string $code): ?Promotion
    {
        $normalized = strtoupper(trim($code));
        if ($normalized === '') {
            return null;
        }

        return Promotion::whereRaw('UPPER(promo_code) = ?', [$normalized])->first();
    }

    /**
     * Validate a promo code for a user against a gross subtotal.
     *
     * @param  array<int, array{service_id: int|string, subtotal: float|int}>|null  $items
     *         the cart's lines; a service-scoped code needs them to know which
     *         part of the cart it may discount
     * @return array{promotion: Promotion, discount: float, eligible_subtotal: float}
     *
     * @throws PromoException when the code cannot be applied
     */
    public static function validate(?User $user, string $code, float $subtotal, ?array $items = null): array
    {
        $promotion = self::findPromotion($code);

        if (!$promotion) {
            throw new PromoException('This promo code is not valid.');
        }

        if (!$promotion->is_active) {
            throw new PromoException('This promo code is no longer active.');
        }

        $now = now();
        if ($promotion->valid_from && $now->lt($promotion->valid_from)) {
            throw new PromoException('This promo code is not active yet.');
        }
        if ($promotion->valid_until && $now->gt($promotion->valid_until)) {
            throw new PromoException('This promo code has expired.');
        }

        // usage_limit = 0 is the admin form's "no cap" value, not "zero uses".
        // Reading it literally made a freshly created code unusable.
        if (
            $promotion->usage_limit !== null
            && (int) $promotion->usage_limit > 0
            && (int) $promotion->usage_count >= (int) $promotion->usage_limit
        ) {
            throw new PromoException('This promo code has reached its usage limit.');
        }

        // A service-scoped code only touches its own line, so both the
        // minimum spend and the discount base are measured against that line -
        // never the whole cart.
        $base = self::eligibleBase($promotion, $subtotal, $items);

        if ($promotion->service_id && $base <= 0) {
            throw new PromoException(sprintf(
                'This promo code only applies to %s, which is not in your cart.',
                $promotion->service?->name ?? 'a specific service'
            ));
        }

        if ($promotion->min_order_value !== null && $base < (float) $promotion->min_order_value) {
            throw new PromoException(sprintf(
                'This promo code requires a minimum order of Rs. %s.',
                number_format((float) $promotion->min_order_value, 0)
            ));
        }

        if ($user && self::hasUserUsed($user, $promotion)) {
            throw new PromoException('You have already used this promo code.');
        }

        $discount = self::discountFor($promotion, $base);

        // A code with no discount configured used to validate happily and take
        // Rs. 0 off: the customer saw "promo applied" and paid full price.
        if ($discount <= 0) {
            throw new PromoException('This promo code is not set up with a discount.');
        }

        return [
            'promotion' => $promotion,
            'discount' => $discount,
            'eligible_subtotal' => $base,
        ];
    }

    /**
     * The part of the cart a promotion may discount.
     *
     * Unscoped codes apply to the whole subtotal. A scoped code applies only
     * to the lines of its service, and returns 0 when the cart has none of it
     * (the caller turns that into a customer-facing reason). Clients that
     * cannot send line items get 0 for a scoped code rather than a discount on
     * the wrong base.
     */
    public static function eligibleBase(Promotion $promotion, float $subtotal, ?array $items = null): float
    {
        if (!$promotion->service_id) {
            return round(max(0, $subtotal), 2);
        }

        if ($items === null) {
            return 0.0;
        }

        $eligible = 0.0;
        foreach ($items as $item) {
            if ((int) ($item['service_id'] ?? 0) === (int) $promotion->service_id) {
                $eligible += (float) ($item['subtotal'] ?? 0);
            }
        }

        return round(max(0, $eligible), 2);
    }

    /**
     * Discount for a promotion on a gross subtotal: percentage or fixed
     * amount, capped by max_discount and never above the subtotal.
     */
    public static function discountFor(Promotion $promotion, float $subtotal): float
    {
        // 'free_service' (and anything else the enum allows) has no math here;
        // reporting 0 lets the caller refuse the code instead of granting a
        // discount of nothing.
        if ($promotion->promo_type !== null
            && !in_array($promotion->promo_type, ['percentage', 'fixed_amount'], true)) {
            return 0.0;
        }

        $isFixed = $promotion->promo_type === 'fixed_amount'
            || ($promotion->discount_percent === null && $promotion->discount_amount !== null);

        $discount = $isFixed
            ? (float) $promotion->discount_amount
            : round($subtotal * ((float) $promotion->discount_percent / 100), 2);

        if ($promotion->max_discount !== null) {
            $discount = min($discount, (float) $promotion->max_discount);
        }

        return round(max(0, min($discount, $subtotal)), 2);
    }

    /**
     * Has this customer already redeemed this code?
     */
    public static function hasUserUsed(User $user, Promotion $promotion): bool
    {
        return DB::table('user_promotions')
            ->where('user_id', $user->user_id)
            ->where('promotion_id', $promotion->promotion_id)
            ->where('used', true)
            ->exists();
    }

    /**
     * Burn one use of the code: bump the global counter and mark the
     * customer's redemption. Call INSIDE the booking transaction so a
     * rollback releases the slot (usage_limit can't be overspent).
     */
    /**
     * Hand a redemption back when the order it paid for never happened
     * (unpaid expiry, customer/admin cancellation). Called from the
     * cancellation paths, which already guarantee the transition happens once,
     * so the counters can never be refunded twice.
     */
    public static function releaseUsage(Order $order): void
    {
        $code = trim((string) $order->promo_code);
        if ($code === '') {
            return;
        }

        $promotion = self::findPromotion($code);
        if (!$promotion) {
            return;
        }

        Promotion::where('promotion_id', $promotion->promotion_id)
            ->where('usage_count', '>', 0)
            ->decrement('usage_count');

        if ($order->user_id) {
            DB::table('user_promotions')
                ->where('user_id', $order->user_id)
                ->where('promotion_id', $promotion->promotion_id)
                ->where('used', true)
                ->update(['used' => false, 'used_at' => null]);
        }
    }

    public static function recordUsage(User $user, Promotion $promotion): void
    {
        Promotion::where('promotion_id', $promotion->promotion_id)->increment('usage_count');

        DB::table('user_promotions')->updateOrInsert(
            ['user_id' => $user->user_id, 'promotion_id' => $promotion->promotion_id],
            ['used' => true, 'used_at' => now()]
        );
    }
}
