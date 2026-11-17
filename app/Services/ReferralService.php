<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Promotion;
use App\Models\ReferralReward;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Customer referral loop:
 *  1. Every customer gets a shareable invite code (lazy-generated).
 *  2. A newcomer redeems it once -> attributed to the referrer and they
 *     BOTH receive single-use welcome/reward promo codes.
 *  3. The referrer's reward promo is issued only when the referee
 *     completes their first booking (anti-fraud: no reward for signups
 *     that never book).
 *
 * All rewards are promo codes - no cash touches the ledger.
 */
class ReferralService
{
    /** Ambiguous glyphs (0/O, 1/I) excluded on purpose. */
    private const CODE_ALPHABET = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';

    /**
     * The user's invite code, generated on first access.
     */
    public static function codeFor(User $user): string
    {
        if ($user->referral_code) {
            return $user->referral_code;
        }

        for ($i = 0; $i < 10; $i++) {
            $candidate = self::randomCode(8);
            if (User::where('referral_code', $candidate)->exists()) {
                continue;
            }

            // Atomic claim: a concurrent call for the same user loses here
            // and must re-read rather than overwrite.
            User::where('user_id', $user->user_id)
                ->whereNull('referral_code')
                ->update(['referral_code' => $candidate]);

            return (string) User::where('user_id', $user->user_id)->value('referral_code');
        }

        throw new ReferralException('Could not generate a referral code. Please try again.');
    }

    /**
     * Redeem someone else's invite code. Sets referred_by exactly once,
     * creates the pending reward and hands the referee their welcome promo.
     *
     * @return array<string, mixed> referral info for the response
     *
     * @throws ReferralException when the code cannot be applied
     */
    public static function applyCode(User $user, string $code): array
    {
        $normalized = strtoupper(trim($code));
        $referrer = User::whereRaw('UPPER(referral_code) = ?', [$normalized])->first();

        if (!$referrer) {
            throw new ReferralException('This referral code is not valid.');
        }
        if ((int) $referrer->user_id === (int) $user->user_id) {
            throw new ReferralException("You can't use your own referral code.");
        }

        // Atomic claim: only the very first redemption wins, no matter
        // how many concurrent requests race here.
        $claimed = User::where('user_id', $user->user_id)
            ->whereNull('referred_by')
            ->update(['referred_by' => $referrer->user_id]);

        if ($claimed === 0) {
            throw new ReferralException('You have already linked a referral code.');
        }

        $welcomeCode = self::issuePromo(
            $user,
            'Welcome bonus',
            "Thanks for joining with a friend's invite code.",
            (int) config('referral.welcome_percent', 15),
            'HI'
        );

        ReferralReward::create([
            'referrer_id' => $referrer->user_id,
            'referee_id' => $user->user_id,
            'welcome_promo_code' => $welcomeCode,
            'status' => 'pending',
        ]);

        NotificationService::send(
            (int) $user->user_id,
            'promotional',
            'Welcome promo unlocked!',
            "Your invite code was applied. Use promo {$welcomeCode} for " .
                (int) config('referral.welcome_percent', 15) .
                '% off your first booking.',
            ['promo_code' => $welcomeCode]
        );

        return self::info($user->fresh());
    }

    /**
     * Called when an order reaches 'completed'. If the customer redeemed
     * an invite code and this is their first completion, issue the
     * referrer's reward promo. Safe to call on every completion.
     */
    public static function onOrderCompleted(Order $order): void
    {
        $reward = ReferralReward::where('referee_id', $order->user_id)
            ->where('status', 'pending')
            ->first();

        if (!$reward) {
            return;
        }

        $referrer = User::find($reward->referrer_id);
        if (!$referrer) {
            return;
        }

        $percent = (int) config('referral.reward_percent', 10);
        $rewardCode = self::issuePromo(
            $referrer,
            'Referral reward',
            'Your friend completed their first booking - enjoy this one on us.',
            $percent,
            'TY'
        );

        $reward->forceFill([
            'status' => 'credited',
            'reward_promo_code' => $rewardCode,
            'credited_at' => now(),
        ])->save();

        NotificationService::send(
            (int) $referrer->user_id,
            'promotional',
            'Referral reward unlocked!',
            "Your friend just completed their first booking. Here's {$percent}% off " .
                "your next booking with code {$rewardCode}.",
            ['promo_code' => $rewardCode]
        );
    }

    /**
     * Snapshot for the Invite & Earn screen.
     *
     * @return array<string, mixed>
     */
    public static function info(User $user): array
    {
        $available = DB::table('user_promotions as up')
            ->join('promotions as p', 'p.promotion_id', '=', 'up.promotion_id')
            ->where('up.user_id', $user->user_id)
            ->where('up.used', false)
            ->where('p.is_active', true)
            ->where('p.valid_from', '<=', now())
            ->where('p.valid_until', '>=', now())
            ->whereRaw('(p.usage_limit IS NULL OR p.usage_count < p.usage_limit)')
            ->orderByDesc('p.created_at')
            ->get()
            ->map(fn ($p) => [
                'code' => $p->promo_code,
                'title' => $p->title,
                'discount_percent' => $p->discount_percent !== null ? (float) $p->discount_percent : null,
                'min_order_value' => $p->min_order_value !== null ? (float) $p->min_order_value : null,
                'valid_until' => $p->valid_until,
            ])
            ->values();

        return [
            'code' => self::codeFor($user),
            'referred_count' => User::where('referred_by', $user->user_id)->count(),
            'rewards_credited' => ReferralReward::where('referrer_id', $user->user_id)
                ->where('status', 'credited')->count(),
            'has_applied_code' => (bool) $user->referred_by,
            'welcome_percent' => (int) config('referral.welcome_percent', 15),
            'reward_percent' => (int) config('referral.reward_percent', 10),
            'min_order_value' => (float) config('referral.min_order_value', 500),
            'validity_days' => (int) config('referral.validity_days', 30),
            'available_promos' => $available,
        ];
    }

    /**
     * Create a single-use, user-assigned percentage promo.
     */
    private static function issuePromo(
        User $user,
        string $title,
        string $description,
        int $percent,
        string $prefix
    ): string {
        $code = null;
        for ($i = 0; $i < 10 && $code === null; $i++) {
            $candidate = $prefix . self::randomCode(6);
            if (!Promotion::where('promo_code', $candidate)->exists()) {
                $code = $candidate;
            }
        }

        if ($code === null) {
            throw new ReferralException('Could not issue promo code. Please try again.');
        }

        $promotion = Promotion::create([
            'promo_code' => $code,
            'title' => $title,
            'description' => $description,
            'promo_type' => 'percentage',
            'discount_percent' => $percent,
            'min_order_value' => (float) config('referral.min_order_value', 500),
            'usage_limit' => 1,
            'usage_count' => 0,
            'valid_from' => now(),
            'valid_until' => now()->addDays((int) config('referral.validity_days', 30)),
            'is_active' => true,
        ]);

        DB::table('user_promotions')->insert([
            'user_id' => $user->user_id,
            'promotion_id' => $promotion->promotion_id,
            'used' => false,
            'created_at' => now(),
        ]);

        return $code;
    }

    private static function randomCode(int $length): string
    {
        $alphabet = self::CODE_ALPHABET;
        $max = strlen($alphabet) - 1;
        $out = '';
        for ($i = 0; $i < $length; $i++) {
            $out .= $alphabet[random_int(0, $max)];
        }
        return $out;
    }
}
