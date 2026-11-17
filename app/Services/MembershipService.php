<?php

namespace App\Services;

use App\Models\MembershipPlan;
use App\Models\User;
use App\Models\UserMembership;
use App\Services\Payment\EsewaGateway;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Urban Plus membership: purchase flow, entitlement lookup and the
 * checkout discount.
 *
 * Money rule (mirrors PromoService): the member percentage applies to the
 * REMAINING base after any promo discount, is capped by the plan's
 * max_discount, and rides inside orders.discount_amount so tax, gateway
 * payloads and settlement stay untouched. orders.membership_discount only
 * records the member share for reporting.
 */
class MembershipService
{
    /**
     * The user's live entitlement, or null. Lazily expires rows whose
     * window has passed so callers never see a stale "active".
     */
    public static function activeFor(User $user): ?UserMembership
    {
        $membership = UserMembership::with('plan')
            ->where('user_id', $user->user_id)
            ->where('status', 'active')
            ->orderByDesc('ends_at')
            ->first();

        if (!$membership) {
            return null;
        }

        if ($membership->ends_at && $membership->ends_at->isPast()) {
            $membership->update(['status' => 'expired']);
            return null;
        }

        return $membership;
    }

    /**
     * Member discount for a base amount (the post-promo remainder):
     * plan percent, capped by max_discount, never above the base.
     */
    public static function discountFor(?UserMembership $membership, float $base): float
    {
        if (!$membership || !$membership->plan || $base <= 0) {
            return 0.0;
        }

        $perks = $membership->plan->perks ?? [];
        $percent = (float) ($perks['discount_percent'] ?? 0);

        if ($percent <= 0) {
            return 0.0;
        }

        $discount = round($base * $percent / 100, 2);

        if (isset($perks['max_discount'])) {
            $discount = min($discount, (float) $perks['max_discount']);
        }

        return round(max(0, min($discount, $base)), 2);
    }

    /**
     * Status payload for the app: active membership (if any) plus its plan.
     */
    public static function statusFor(User $user): ?array
    {
        $membership = self::activeFor($user);

        if (!$membership || !$membership->plan) {
            return null;
        }

        $plan = $membership->plan;
        $perks = $plan->perks ?? [];

        // Epoch math avoids Carbon's signed-diff direction quirks.
        $secondsLeft = $membership->ends_at
            ? $membership->ends_at->getTimestamp() - now()->getTimestamp()
            : 0;

        return [
            'status' => 'active',
            'starts_at' => optional($membership->starts_at)->toIso8601String(),
            'ends_at' => optional($membership->ends_at)->toIso8601String(),
            'days_left' => max(0, (int) ceil($secondsLeft / 86400)),
            'discount_percent' => (float) ($perks['discount_percent'] ?? 0),
            'max_discount' => isset($perks['max_discount']) ? (float) $perks['max_discount'] : null,
            'plan' => [
                'plan_id' => $plan->id,
                'code' => $plan->code,
                'name' => $plan->name,
                'description' => $plan->description,
                'price' => (float) $plan->price,
                'duration_days' => $plan->duration_days,
                'perks' => $perks,
            ],
        ];
    }

    /**
     * Start an eSewa purchase for a plan: one pending attempt at a time,
     * then the signed form payload the app posts through the WebView.
     *
     * No HTTP happens here - eSewa initiate is a pure form build.
     */
    public static function initiateEsewa(User $user, MembershipPlan $plan): array
    {
        // Only the newest attempt may be paid - stale pendings would make
        // the txn lookup ambiguous.
        UserMembership::where('user_id', $user->user_id)
            ->where('status', 'pending')
            ->update(['status' => 'failed']);

        $membership = UserMembership::create([
            'user_id' => $user->user_id,
            'plan_id' => $plan->id,
            'status' => 'pending',
            'price_paid' => $plan->price,
            'gateway' => 'esewa',
            'txn_id' => 'MBR-' . Str::uuid()->toString(),
            'payment_expires_at' => now()->addMinutes(
                (int) config('membership.pending_minutes', 15)
            ),
        ]);

        // eSewa validates the signature against the EXACT posted string -
        // normalize once and reuse everywhere (see EsewaGateway).
        $totalAmount = number_format((float) $plan->price, 2, '.', '');
        $uuid = $membership->txn_id;

        $gateway = new EsewaGateway('esewa');

        $payload = [
            'amount' => $totalAmount,
            'failure_url' => route('membership.webhook.failure'),
            'product_delivery_charge' => '0',
            'product_service_charge' => '0',
            'product_code' => config('payment.gateways.esewa.product_code'),
            'signature' => $gateway->generateSignature($totalAmount, $uuid),
            'signed_field_names' => 'total_amount,transaction_uuid,product_code',
            'success_url' => route('membership.webhook.success'),
            'tax_amount' => '0',
            'total_amount' => $totalAmount,
            'transaction_uuid' => $uuid,
        ];

        return [
            'success' => true,
            'payment_url' => $gateway->getRedirectUrl($uuid),
            'payload' => $payload,
            'membership_id' => $membership->id,
        ];
    }

    /**
     * Verify the gateway result for a membership txn and activate the
     * entitlement window. Idempotent: replayed success callbacks return
     * already_paid without touching the row.
     */
    public static function verifyAndActivate(array $data): array
    {
        $txn = (string) ($data['transaction_uuid'] ?? '');

        if ($txn === '') {
            return ['success' => false, 'error' => 'Missing transaction_uuid'];
        }

        $membership = UserMembership::where('txn_id', $txn)->first();

        if (!$membership) {
            return ['success' => false, 'error' => 'Unknown membership transaction'];
        }

        if ($membership->status === 'active') {
            return ['success' => true, 'already_paid' => true, 'membership' => $membership];
        }

        if ($membership->status !== 'pending') {
            return ['success' => false, 'error' => 'Membership payment is not pending'];
        }

        $gateway = new EsewaGateway('esewa');
        $verified = $gateway->verifyPayment($txn, [
            'total_amount' => number_format((float) $membership->price_paid, 2, '.', ''),
        ]);

        if (!($verified['success'] ?? false)) {
            Log::warning('Membership eSewa verification failed', [
                'txn_id' => $txn,
                'error' => $verified['error'] ?? 'unknown',
                'status' => $verified['status'] ?? null,
            ]);

            return ['success' => false, 'error' => $verified['error'] ?? 'Payment verification failed'];
        }

        return DB::transaction(function () use ($membership) {
            $locked = UserMembership::where('id', $membership->id)->lockForUpdate()->first();

            // Race guard: a replay may have activated while we verified.
            if ($locked->status === 'active') {
                return ['success' => true, 'already_paid' => true, 'membership' => $locked];
            }

            $locked->update([
                'status' => 'active',
                'starts_at' => now(),
                'ends_at' => now()->addDays((int) $locked->plan->duration_days),
            ]);

            return ['success' => true, 'membership' => $locked];
        });
    }

    /** Terminal-fail a pending purchase (gateway failure callback). */
    public static function markFailed(string $txn): void
    {
        UserMembership::where('txn_id', $txn)
            ->where('status', 'pending')
            ->update(['status' => 'failed']);
    }
}
