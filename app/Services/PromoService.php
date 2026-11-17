<?php

namespace App\Services;

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
     * @return array{promotion: Promotion, discount: float}
     *
     * @throws PromoException when the code cannot be applied
     */
    public static function validate(?User $user, string $code, float $subtotal): array
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

        if ($promotion->min_order_value !== null && $subtotal < (float) $promotion->min_order_value) {
            throw new PromoException(sprintf(
                'This promo code requires a minimum order of Rs. %s.',
                number_format((float) $promotion->min_order_value, 0)
            ));
        }

        if ($user && self::hasUserUsed($user, $promotion)) {
            throw new PromoException('You have already used this promo code.');
        }

        return [
            'promotion' => $promotion,
            'discount' => self::discountFor($promotion, $subtotal),
        ];
    }

    /**
     * Discount for a promotion on a gross subtotal: percentage or fixed
     * amount, capped by max_discount and never above the subtotal.
     */
    public static function discountFor(Promotion $promotion, float $subtotal): float
    {
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
    public static function recordUsage(User $user, Promotion $promotion): void
    {
        Promotion::where('promotion_id', $promotion->promotion_id)->increment('usage_count');

        DB::table('user_promotions')->updateOrInsert(
            ['user_id' => $user->user_id, 'promotion_id' => $promotion->promotion_id],
            ['used' => true, 'used_at' => now()]
        );
    }
}
