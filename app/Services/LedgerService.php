<?php

namespace App\Services;

use App\Models\LedgerEntry;
use App\Support\SchemaCompat;
use App\Models\Order;
use App\Models\PartnerBalance;
use App\Models\PartnerPayout;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class LedgerService
{
    /**
     * Serialize concurrent balance_after chain writes for one user.
     *
     * Every ledger chain is read-compute-write: two concurrent writers for
     * the same user would both read the SAME last balance_after and both
     * write, corrupting the running chain. Locking the users row (always
     * exists, and ledger_entries has a FK to it) gives a per-user
     * serialization point inside the caller's transaction.
     *
     * MUST be called as the INNERMOST lock in any transaction (after the
     * order/payment/payout row) so lock ordering is always consistent.
     */
    private function lockUserChain(int $userId): void
    {
        \App\Models\User::whereKey($userId)->lockForUpdate()->first();
    }

    /**
     * Step 5: Job completion - record partner earning and update partner balance
     * Remaining balance should equal partner earning
     */
    public function recordPartnerEarning(string $bookingId, int $partnerId, float $partnerEarning, array $context = []): void
    {
        DB::beginTransaction();
        try {
            $this->lockUserChain($partnerId);
            // Get partner's current balance from their personal ledger entries
            $partnerCurrentBalance = LedgerEntry::where('user_id', $partnerId)
                ->orderByDesc('created_at')
                ->orderByDesc('id')
                ->value('balance_after') ?? 0;

            // Partner earnings are cumulative - add to partner's balance
            $newPartnerBalance = $partnerCurrentBalance + $partnerEarning;

            // Get order details for comprehensive narration
            $order = \App\Models\Order::with(['user', 'items.service'])->find($bookingId);
            $partner = \App\Models\User::find($partnerId);
            $customer = $order->user;
            $completionDate = now()->format('Y-m-d H:i:s T');

            // Generate service breakdown
            $serviceBreakdown = $order->items->map(function ($item) {
                return "{$item->service->name} x{$item->quantity}";
            })->implode(', ');

            $platformFee = $context['platform_fee'] ?? ($order->subtotal - $partnerEarning);
            $commissionRate = $order->subtotal > 0 ? round(($partnerEarning / $order->subtotal) * 100, 2) : 0;
            $commissionRule = $context['commission_rule_used'] ?? 'standard';

            $detailedNarration = "PARTNER EARNING: Service completion payment for Order #{$bookingId}. " .
                "Partner: {$partner->first_name} {$partner->last_name} (ID: {$partnerId}). " .
                "Customer: {$customer->first_name} {$customer->last_name} (ID: {$customer->user_id}). " .
                "Services Completed: {$serviceBreakdown}. " .
                "Order Subtotal: NPR " . number_format($order->subtotal, 2) . ". " .
                "Partner Commission: {$commissionRate}% (NPR " . number_format($partnerEarning, 2) . "). " .
                "Platform Fee: NPR " . number_format($platformFee, 2) . ". " .
                "Commission Rule: {$commissionRule}. " .
                "Job Completion Date: {$completionDate}. " .
                "Payment Status: Credited to partner available balance. " .
                "Note: Amount available for immediate withdrawal upon request.";

            // Create ledger entry
            $this->createLedgerEntry([
                'booking_id' => $bookingId,
                'user_id' => $partnerId,
                'type' => 'PARTNER_EARNING',
                'amount' => $partnerEarning,
                'balance_after' => $newPartnerBalance,
                'narration' => $detailedNarration,
                'metadata' => [
                    'commission_rule_id' => is_numeric($commissionRule) ? (int) $commissionRule : null,
                    'partner_pct' => $context['partner_pct'] ?? null,
                    'subtotal' => (float) ($context['subtotal'] ?? $order->subtotal),
                    'service_id' => $context['service_id'] ?? null,
                ],
            ]);

            // PREPAID MODEL: Update partner balance - add directly to available
            $this->updatePartnerBalance($partnerId, $partnerEarning, $partnerEarning, 0);

            DB::commit();

            Log::info("Partner earning recorded", [
                'booking_id' => $bookingId,
                'partner_id' => $partnerId,
                'amount' => $partnerEarning,
                'partner_previous_balance' => $partnerCurrentBalance,
                'partner_new_balance' => $newPartnerBalance
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Record a customer refund for a cancelled online-paid booking.
     * If the partner had already been paid for this booking (job completed
     * before cancellation), the partner earning is clawed back.
     */
    public function recordRefund(string $bookingId, int $customerId, float $amount, array $context = []): void
    {
        $this->lockUserChain($customerId);

        // Prevent duplicate refunds for the same booking
        $existingRefund = LedgerEntry::where('booking_id', $bookingId)
            ->where('user_id', $customerId)
            ->where('type', 'REFUND')
            ->exists();

        if ($existingRefund) {
            Log::warning('Duplicate refund prevented', [
                'booking_id' => $bookingId,
                'customer_id' => $customerId,
                'amount' => $amount
            ]);
            return;
        }

        // The running balance is per USER, not per booking: use the
        // customer's own last balance_after.
        $customerCurrentBalance = LedgerEntry::where('user_id', $customerId)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->value('balance_after') ?? 0;

        $this->createLedgerEntry([
            'booking_id' => $bookingId,
            'user_id' => $customerId,
            'type' => 'REFUND',
            'amount' => $amount,
            'balance_after' => $customerCurrentBalance + $amount,
            'narration' => "Refund to customer for cancelled booking {$bookingId}. Amount: NPR " . number_format($amount, 2) . ". Reason: " . ($context['reason'] ?? 'Booking cancelled'),
            'metadata' => ['reversal_reason' => $context['reason'] ?? 'Booking cancelled'],
        ]);

        // Claw back any partner earnings already paid for this booking so the
        // platform does not pay partners for work that was refunded.
        $partnerEarnings = LedgerEntry::where('booking_id', $bookingId)
            ->where('type', 'PARTNER_EARNING')
            ->get();

        foreach ($partnerEarnings as $earning) {
            $this->recordEarningReversal($bookingId, (int) $earning->user_id, (float) $earning->amount, [
                'reason' => $context['reason'] ?? 'Booking cancelled',
            ]);
        }

        // Reverse the platform-side entries posted at payment time
        // (ESCROW_CREDIT / PLATFORM_FEE / TAX_PAYABLE). Without this the
        // platform books revenue on money it returned to the customer.
        // Reversals reuse the SAME type + metadata->dispute_reversal=true so
        // they are exempt from the non-reversal unique index and stay
        // attached to the booking they reverse.
        $platformRows = LedgerEntry::where('booking_id', $bookingId)
            ->whereIn('type', ['ESCROW_CREDIT', 'PLATFORM_FEE', 'TAX_PAYABLE'])
            ->whereRaw(SchemaCompat::jsonFlagExpression('metadata', 'dispute_reversal') . " IS DISTINCT FROM 'true'")
            ->get();

        foreach ($platformRows as $row) {
            $userLastBalance = LedgerEntry::where('user_id', $row->user_id)
                ->orderByDesc('created_at')
                ->orderByDesc('id')
                ->value('balance_after') ?? 0;

            $reversalAmount = -((float) $row->amount);

            $this->createLedgerEntry([
                'booking_id' => $bookingId,
                'user_id' => $row->user_id,
                'type' => $row->type,
                'amount' => $reversalAmount,
                'balance_after' => (float) $userLastBalance + LedgerEntry::signedAmount($row->type, $reversalAmount),
                'narration' => "{$row->type} REVERSED: booking #{$bookingId} refunded to customer (NPR " . number_format($amount, 2) . "). Reason: " . ($context['reason'] ?? 'Booking cancelled'),
                'metadata' => [
                    'dispute_reversal' => 'true',
                    'reversal_reason' => $context['reason'] ?? 'Booking cancelled',
                    'reversal_of_entry_id' => $row->id,
                ],
            ]);
        }

        Log::info('Customer refund recorded', [
            'booking_id' => $bookingId,
            'customer_id' => $customerId,
            'amount' => $amount,
            'partner_earnings_reversed' => $partnerEarnings->count(),
            'platform_entries_reversed' => $platformRows->count(),
            'reason' => $context['reason'] ?? 'Booking cancelled'
        ]);
    }

    /**
     * Reverse a previously recorded partner earning (refund clawback).
     * Idempotent per (booking, partner).
     */
    public function recordEarningReversal(string $bookingId, int $partnerId, float $amount, array $context = []): void
    {
        $this->lockUserChain($partnerId);

        $existing = LedgerEntry::where('booking_id', $bookingId)
            ->where('user_id', $partnerId)
            ->where('type', 'EARNING_REVERSAL')
            ->exists();

        if ($existing) {
            Log::warning('Duplicate earning reversal prevented', [
                'booking_id' => $bookingId,
                'partner_id' => $partnerId,
                'amount' => $amount
            ]);
            return;
        }

        $partnerCurrentBalance = LedgerEntry::where('user_id', $partnerId)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->value('balance_after') ?? 0;

        $this->createLedgerEntry([
            'booking_id' => $bookingId,
            'user_id' => $partnerId,
            'type' => 'EARNING_REVERSAL',
            'amount' => $amount,
            'balance_after' => $partnerCurrentBalance - $amount,
            'narration' => "Partner earning reversed for refunded booking {$bookingId}. Amount: NPR " . number_format($amount, 2) . ". Reason: " . ($context['reason'] ?? 'Booking cancelled'),
            'metadata' => ['reason' => $context['reason'] ?? 'Booking cancelled'],
        ]);

        // PREPAID MODEL: take the money back out of the partner's balance
        $this->updatePartnerBalance($partnerId, -$amount, -$amount, 0);

        Log::info('Partner earning reversal recorded', [
            'booking_id' => $bookingId,
            'partner_id' => $partnerId,
            'amount' => $amount
        ]);
    }

    /**
     * Record PAS cash payment receipt - partner received full payment in cash
     * from the customer for a Pay After Service order.
     *
     * MODEL A: the partner keeps the cash, so the receipt (earnings history)
     * is immediately followed by a PAS_CASH_SETTLEMENT that removes the
     * cash-collected share from the available balance, and the platform
     * commission is deducted at completion. Net effect on the available
     * balance per cash-settled PAS job: -commission (money the partner owes
     * the platform).
     *
     * Called only AFTER the customer confirms the cash declaration
     * (confirm-cash endpoint). Until then the settlement is pending and the
     * partner balance is untouched.
     */
    public function recordPASReceipt(int $partnerId, string $bookingId, float $amount, array $context = []): void
    {
        DB::beginTransaction();
        try {
            $this->lockUserChain($partnerId);
            // Prevent duplicate PAS receipts for the same booking
            $existingReceipt = LedgerEntry::where('booking_id', $bookingId)
                ->where('user_id', $partnerId)
                ->where('type', 'PAS_RECEIPT')
                ->whereRaw(SchemaCompat::jsonFlagExpression('metadata', 'dispute_reversal') . " IS DISTINCT FROM 'true'")
                ->exists();

            if ($existingReceipt) {
                DB::rollBack();
                Log::warning('Duplicate PAS receipt prevented', [
                    'booking_id' => $bookingId,
                    'partner_id' => $partnerId,
                    'amount' => $amount
                ]);
                return;
            }

            // Resolve the commission rule snapshot (A6): prefer a caller-provided
            // snapshot, otherwise take the rule currently in effect.
            $commissionRule = $context['commission_rule'] ?? null;
            $subtotal = $context['subtotal'] ?? null;
            if (!$commissionRule && $subtotal === null) {
                $order = \App\Models\Order::find($bookingId);
                $subtotal = $order?->subtotal;
            }
            if (!$commissionRule && $subtotal !== null) {
                $orderItem = \App\Models\Order::find($bookingId)?->items()->first();
                $serviceId = $context['service_id'] ?? ($orderItem ? $orderItem->service_id : null);
                if ($serviceId) {
                    $commissionRule = \App\Models\CommissionRule::findApplicableRule(
                        $serviceId,
                        null,
                        (float) $subtotal,
                        new \DateTime()
                    );
                }
            }

            $partnerPct = $commissionRule ? (float) $commissionRule->partner_pct : 80.0;
            $partnerShare = round((float) $subtotal * ($partnerPct / 100), 2);

            // Get partner's current balance
            $partnerCurrentBalance = LedgerEntry::where('user_id', $partnerId)
                ->orderByDesc('created_at')
                ->orderByDesc('id')
                ->value('balance_after') ?? 0;

            // Add full payment to partner's balance
            $newPartnerBalance = $partnerCurrentBalance + $amount;

            // Get order details for narration
            $order = \App\Models\Order::with(['user', 'items.service'])->find($bookingId);
            $partner = \App\Models\User::find($partnerId);
            $customer = $order->user;
            $completionDate = now()->format('Y-m-d H:i:s T');

            // Generate service breakdown
            $serviceBreakdown = $order->items->map(function ($item) {
                return "{$item->service->name} x{$item->quantity}";
            })->implode(', ');

            $detailedNarration = "PAS PAYMENT RECEIPT: Partner received full payment from customer for Order #{$bookingId}. " .
                "Partner: {$partner->first_name} {$partner->last_name} (ID: {$partnerId}). " .
                "Customer: {$customer->first_name} {$customer->last_name} (ID: {$customer->user_id}). " .
                "Services Completed: {$serviceBreakdown}. " .
                "Payment Amount: NPR " . number_format($amount, 2) . ". " .
                "Payment Method: Pay After Service (PAS) - Cash collected at completion. " .
                "Job Completion Date: {$completionDate}. " .
                "Note: Partner received cash directly from customer. Platform commission deducted on completion.";

            // Create ledger entry for PAS receipt
            $this->createLedgerEntry([
                'booking_id' => $bookingId,
                'user_id' => $partnerId,
                'type' => 'PAS_RECEIPT',
                'amount' => $amount,
                'balance_after' => $newPartnerBalance,
                'narration' => $detailedNarration,
                'metadata' => [
                    'commission_rule_id' => $commissionRule ? $commissionRule->rule_id : null,
                    'partner_pct' => $partnerPct,
                    'subtotal' => (float) $subtotal,
                    'commission_deducted_at_completion' => true,
                ],
            ]);

            // Update partner balance - add to available balance
            $this->updatePartnerBalance($partnerId, $amount, $amount, 0);

            // MODEL A (cash kept by partner): the partner collected the full
            // amount in cash from the customer, so their share is already
            // settled. Remove the cash-collected share from the available
            // balance - the platform owes the partner nothing for this job.
            // Earnings history still reflects the full receipt above.
            $this->createLedgerEntry([
                'booking_id' => $bookingId,
                'user_id' => $partnerId,
                'type' => 'PAS_CASH_SETTLEMENT',
                'amount' => -$amount,
                'balance_after' => $partnerCurrentBalance,
                'narration' => "PAS CASH SETTLEMENT: Order #{$bookingId} - partner collected NPR " .
                    number_format($amount, 2) . " in cash from customer. Partner share already " .
                    "received in cash; no platform credit remains for this job.",
                'metadata' => [
                    'commission_rule_id' => $commissionRule ? $commissionRule->rule_id : null,
                    'partner_pct' => $partnerPct,
                    'subtotal' => (float) $subtotal,
                ],
            ]);

            // The settlement does not change earnings, only the available balance
            $this->updatePartnerBalance($partnerId, -$amount, 0, 0);

            // Deduct the platform commission immediately so the available balance
            // reflects the partner's true net share (A7).
            $platformDeduction = max(0, (float) $amount - $partnerShare);
            if ($platformDeduction > 0) {
                $existingCommission = LedgerEntry::where('booking_id', $bookingId)
                    ->where('user_id', $partnerId)
                    ->where('type', 'PAS_COMMISSION')
                    ->exists();

                if (!$existingCommission) {
                    $this->recordPASCommissionDeduction($bookingId, $partnerId, $platformDeduction, [
                        'commission_rule_used' => $commissionRule ? $commissionRule->rule_id : 'fallback',
                        'partner_pct' => $partnerPct,
                        'subtotal' => (float) $subtotal,
                        'deducted_at_completion' => true,
                    ]);
                }
            }

            DB::commit();

            Log::info("PAS payment receipt recorded", [
                'booking_id' => $bookingId,
                'partner_id' => $partnerId,
                'amount' => $amount,
                'commission_deducted' => $platformDeduction,
                'partner_previous_balance' => $partnerCurrentBalance,
                'partner_new_balance' => $newPartnerBalance
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    public function recordPASCommissionDeduction(string $bookingId, int $partnerId, float $commissionAmount, array $context = []): void
    {
        DB::beginTransaction();
        try {
            $this->lockUserChain($partnerId);
            // Get partner's current balance from their personal ledger entries
            $partnerCurrentBalance = LedgerEntry::where('user_id', $partnerId)
                ->orderByDesc('created_at')
                ->orderByDesc('id')
                ->value('balance_after') ?? 0;

            // Deduct commission from partner's balance
            $newPartnerBalance = $partnerCurrentBalance - $commissionAmount;

            // Get order details for comprehensive narration
            $order = \App\Models\Order::with(['user', 'items.service'])->find($bookingId);
            $partner = \App\Models\User::find($partnerId);
            $customer = $order->user;
            $completionDate = now()->format('Y-m-d H:i:s T');

            // Generate service breakdown
            $serviceBreakdown = $order->items->map(function ($item) {
                return "{$item->service->name} x{$item->quantity}";
            })->implode(', ');

            $commissionRate = $order->subtotal > 0 ? round(($commissionAmount / $order->subtotal) * 100, 2) : 0;
            $commissionRule = $context['commission_rule_used'] ?? 'standard';

            $detailedNarration = "PAS PLATFORM CUT: Platform share (commission + service charge + platform fee + tax) for Order #{$bookingId}. " .
                "Partner: {$partner->first_name} {$partner->last_name} (ID: {$partnerId}). " .
                "Customer: {$customer->first_name} {$customer->last_name} (ID: {$customer->user_id}). " .
                "Services Completed: {$serviceBreakdown}. " .
                "Order Subtotal: NPR " . number_format($order->subtotal, 2) . ". " .
                "Cash Collected (Total): NPR " . number_format($order->total_amount, 2) . ". " .
                "Platform Cut Rate: {$commissionRate}% of subtotal (NPR " . number_format($commissionAmount, 2) . "). " .
                "Commission Rule: {$commissionRule}. " .
                "Payment Method: Pay After Service (PAS) - cash collected at completion. " .
                "Job Completion Date: {$completionDate}. " .
                "Note: Partner received full payment from customer; platform cut deducted from available balance. Partner net share matches online orders.";

            // Create ledger entry for commission deduction
            $this->createLedgerEntry([
                'booking_id' => $bookingId,
                'user_id' => $partnerId,
                'type' => 'PAS_COMMISSION',
                'amount' => $commissionAmount,
                'balance_after' => $newPartnerBalance,
                'narration' => $detailedNarration,
                'metadata' => [
                    'commission_rule_id' => $context['commission_rule_id'] ?? ($context['commission_rule_used'] !== 'fallback' ? $context['commission_rule_used'] : null),
                    'partner_pct' => $context['partner_pct'] ?? null,
                    'subtotal' => $context['subtotal'] ?? (float) $order->subtotal,
                    'deducted_at_completion' => $context['deducted_at_completion'] ?? false,
                    'deducted_at_payout' => $context['deducted_at_payout'] ?? false,
                ],
            ]);

            // Update partner balance - deduct from available balance
            $this->updatePartnerBalance($partnerId, -$commissionAmount, -$commissionAmount, 0);

            // Mark commission as deducted in order
            $order->update([
                'commission_deducted' => true,
                'commission_deducted_at' => now()
            ]);

            DB::commit();

            Log::info("PAS commission deduction recorded", [
                'booking_id' => $bookingId,
                'partner_id' => $partnerId,
                'commission_amount' => $commissionAmount,
                'partner_previous_balance' => $partnerCurrentBalance,
                'partner_new_balance' => $newPartnerBalance
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Reverse a PAS cash settlement when the customer disputes the cash
     * declaration. Posts exact opposite-sign entries for the receipt,
     * cash settlement and commission so the partner's balance and earnings
     * for the job return to zero, pending admin resolution.
     *
     * Idempotent: runs once per booking+partner (guarded by the reversal
     * marker on the receipt reversal entry).
     */
    public function reversePASCashSettlement(string $bookingId, int $partnerId): void
    {
        DB::beginTransaction();
        try {
            $this->lockUserChain($partnerId);
            $alreadyReversed = LedgerEntry::where('booking_id', $bookingId)
                ->where('user_id', $partnerId)
                ->where('type', 'PAS_RECEIPT')
                ->whereRaw(SchemaCompat::jsonFlagExpression('metadata', 'dispute_reversal') . " = 'true'")
                ->exists();

            if ($alreadyReversed) {
                DB::rollBack();
                Log::warning('Duplicate PAS dispute reversal prevented', [
                    'booking_id' => $bookingId,
                    'partner_id' => $partnerId,
                ]);
                return;
            }

            $originalReceipt = LedgerEntry::where('booking_id', $bookingId)
                ->where('user_id', $partnerId)
                ->where('type', 'PAS_RECEIPT')
                ->first();

            if (!$originalReceipt) {
                // Settlement written by PasSettlementService: the partner earned
                // on the gross value and holds the cash instead of a receipt.
                $this->reverseGrossPasSettlement($bookingId, $partnerId);
                DB::commit();

                return;
            }

            $amount = (float) $originalReceipt->amount;

            // Original commission deduction (positive amount) if it exists.
            $originalCommission = LedgerEntry::where('booking_id', $bookingId)
                ->where('user_id', $partnerId)
                ->where('type', 'PAS_COMMISSION')
                ->first();
            $commission = $originalCommission ? (float) $originalCommission->amount : 0.0;

            // Running balance from the partner's latest entry.
            $currentBalance = LedgerEntry::where('user_id', $partnerId)
                ->orderByDesc('created_at')
                ->orderByDesc('id')
                ->value('balance_after') ?? 0;
            $currentBalance = (float) $currentBalance;

            $order = \App\Models\Order::find($bookingId);
            $reason = 'customer dispute';

            $reversals = [
                [
                    'type' => 'PAS_RECEIPT',
                    'amount' => -$amount,
                    'narration' => "PAS RECEIPT REVERSED: Order #{$bookingId} - cash receipt reversed after {$reason}. No earning remains for this job pending dispute resolution.",
                ],
                [
                    'type' => 'PAS_CASH_SETTLEMENT',
                    'amount' => $amount,
                    'narration' => "PAS CASH SETTLEMENT REVERSED: Order #{$bookingId} - cash-collected share restored after {$reason}.",
                ],
            ];

            if ($commission > 0) {
                $reversals[] = [
                    'type' => 'PAS_COMMISSION',
                    'amount' => -$commission,
                    'narration' => "PAS COMMISSION REVERSED: Order #{$bookingId} - platform commission refunded after {$reason}.",
                ];
            }

            foreach ($reversals as $reversal) {
                $currentBalance += LedgerEntry::signedAmount($reversal['type'], (float) $reversal['amount']);
                $this->createLedgerEntry([
                    'booking_id' => $bookingId,
                    'user_id' => $partnerId,
                    'type' => $reversal['type'],
                    'amount' => $reversal['amount'],
                    'balance_after' => $currentBalance,
                    'narration' => $reversal['narration'],
                    'metadata' => [
                        'dispute_reversal' => true,
                        'dispute_reason' => $order?->cash_disputed_at ? 'customer dispute' : 'admin correction',
                        'reversed_at' => now()->toDateTimeString(),
                    ],
                ]);
            }

            // Mirror the reversal in the cached PartnerBalance.
            $this->updatePartnerBalance($partnerId, -$amount, -$amount, 0); // receipt reversal
            $this->updatePartnerBalance($partnerId, $amount, 0, 0);         // settlement reversal
            if ($commission > 0) {
                $this->updatePartnerBalance($partnerId, $commission, $commission, 0); // commission reversal
            }

            DB::commit();

            Log::warning('PAS cash settlement reversed after dispute', [
                'booking_id' => $bookingId,
                'partner_id' => $partnerId,
                'amount' => $amount,
                'commission' => $commission,
                'balance_after' => $currentBalance,
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Reverse the gross-based PAS settlement shape (PARTNER_EARNING + negative
     * PAS_CASH_SETTLEMENT) inside the caller's transaction.
     *
     * The platform legs (fee / tax / discount) are left in place deliberately:
     * on a multi-partner order a single partner's dispute must not erase the
     * whole order's revenue. Those are corrected from the ledger once the
     * dispute is resolved.
     */
    private function reverseGrossPasSettlement(string $bookingId, int $partnerId): void
    {
        $earning = LedgerEntry::where('booking_id', $bookingId)
            ->where('user_id', $partnerId)
            ->where('type', 'PARTNER_EARNING')
            ->first();

        if (!$earning) {
            Log::warning('No PAS settlement found to reverse', [
                'booking_id' => $bookingId,
                'partner_id' => $partnerId,
            ]);

            return;
        }

        $earningAmount = (float) $earning->amount;

        $settlement = LedgerEntry::where('booking_id', $bookingId)
            ->where('user_id', $partnerId)
            ->where('type', 'PAS_CASH_SETTLEMENT')
            ->first();
        $cashHeld = $settlement ? abs((float) $settlement->amount) : 0.0;

        $currentBalance = (float) (LedgerEntry::where('user_id', $partnerId)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->value('balance_after') ?? 0);

        $currentBalance += LedgerEntry::signedAmount('EARNING_REVERSAL', $earningAmount);

        $this->createLedgerEntry([
            'booking_id' => $bookingId,
            'user_id' => $partnerId,
            'type' => 'EARNING_REVERSAL',
            'amount' => $earningAmount,
            'balance_after' => $currentBalance,
            'narration' => "PAS EARNING REVERSED: order #{$bookingId} - the partner's share is withdrawn while the cash handover is disputed.",
            'metadata' => [
                'dispute_reversal' => true,
                'reversed_at' => now()->toDateTimeString(),
            ],
        ]);

        $this->updatePartnerBalance($partnerId, -$earningAmount, -$earningAmount, 0);

        if ($cashHeld > 0) {
            $currentBalance += $cashHeld;

            $this->createLedgerEntry([
                'booking_id' => $bookingId,
                'user_id' => $partnerId,
                'type' => 'PAS_CASH_SETTLEMENT',
                'amount' => $cashHeld,
                'balance_after' => $currentBalance,
                'narration' => "PAS CASH SETTLEMENT REVERSED: order #{$bookingId} - the cash the partner was holding is no longer counted against them.",
                'metadata' => [
                    'dispute_reversal' => true,
                    'reversed_at' => now()->toDateTimeString(),
                ],
            ]);

            $this->updatePartnerBalance($partnerId, $cashHeld, 0, 0);
        }

        Log::warning('PAS gross settlement reversed', [
            'booking_id' => $bookingId,
            'partner_id' => $partnerId,
            'earning' => $earningAmount,
            'cash_held' => $cashHeld,
        ]);
    }

    /**
     * Deduct pending PAS commissions for a partner when processing payout.
     * Safety net for legacy PAS receipts recorded before commissions were
     * deducted at completion time. Uses the commission rule snapshot stored
     * on the receipt when available, otherwise the rule in effect today.
     */
    public function deductPendingPASCommissions(int $partnerId): float
    {
        // Find all PAS_RECEIPT entries that don't have corresponding PAS_COMMISSION deductions
        $pasReceiptsWithoutCommission = DB::table('ledger_entries')
            ->where('user_id', $partnerId)
            ->where('type', 'PAS_RECEIPT')
            ->whereNotExists(function ($query) use ($partnerId) {
                $query->select(DB::raw(1))
                    ->from('ledger_entries as commissions')
                    ->whereRaw('commissions.booking_id = ledger_entries.booking_id')
                    ->where('commissions.user_id', '=', $partnerId)
                    ->where('commissions.type', '=', 'PAS_COMMISSION');
            })
            ->get();

        $totalCommissionDeducted = 0;

        foreach ($pasReceiptsWithoutCommission as $pasReceipt) {
            // MODEL A: legacy receipts were full-credited without a cash
            // settlement. Insert the settlement (once) so the available balance
            // reflects that the partner kept the cash. Skip jobs that were
            // refunded (their receipt was already reversed).
            $settlementExists = LedgerEntry::where('booking_id', $pasReceipt->booking_id)
                ->where('user_id', $partnerId)
                ->where('type', 'PAS_CASH_SETTLEMENT')
                ->exists();

            if (!$settlementExists) {
                $refunded = LedgerEntry::where('booking_id', $pasReceipt->booking_id)
                    ->where('user_id', $partnerId)
                    ->where('type', 'EARNING_REVERSAL')
                    ->exists();

                if (!$refunded) {
                    $settlementAmount = abs((float) $pasReceipt->amount);
                    $lastBalance = LedgerEntry::where('user_id', $partnerId)
                        ->orderByDesc('created_at')
                        ->orderByDesc('id')
                        ->value('balance_after') ?? 0;

                    $this->createLedgerEntry([
                        'booking_id' => $pasReceipt->booking_id,
                        'user_id' => $partnerId,
                        'type' => 'PAS_CASH_SETTLEMENT',
                        'amount' => -$settlementAmount,
                        'balance_after' => $lastBalance - $settlementAmount,
                        'narration' => "PAS CASH SETTLEMENT: Order #{$pasReceipt->booking_id} - partner collected cash from customer; share settled in cash (backfilled at payout).",
                        'metadata' => ['backfilled' => true, 'source' => 'deductPendingPASCommissions'],
                    ]);

                    $this->updatePartnerBalance($partnerId, -$settlementAmount, 0, 0);
                }
            }

            $order = \App\Models\Order::find($pasReceipt->booking_id);
            if (!$order) continue;

            $orderItem = $order->items()->first();
            $serviceId = $orderItem ? $orderItem->service_id : null;

            // Prefer the commission snapshot taken at completion time so a rule
            // change between completion and payout cannot alter the partner's net.
            $snapshot = json_decode($pasReceipt->metadata ?? 'null', true) ?? [];
            if (!empty($snapshot['subtotal']) && isset($snapshot['partner_pct'])) {
                $partnerShare = round((float) $snapshot['subtotal'] * ((float) $snapshot['partner_pct'] / 100), 2);
                $ruleId = $snapshot['commission_rule_id'] ?? 'fallback';
            } else {
                $partnerShare = 0;
                $ruleId = 'fallback';
                if ($serviceId) {
                    $commissionRule = \App\Models\CommissionRule::findApplicableRule(
                        $serviceId,
                        null, // city
                        $order->subtotal,
                        new \DateTime()
                    );

                    if ($commissionRule) {
                        $shares = $commissionRule->calculateShares($order->subtotal);
                        $partnerShare = $shares['partner_share'];
                        $ruleId = $commissionRule->rule_id;
                    } else {
                        $partnerShare = $order->subtotal * 0.80;
                    }
                } else {
                    $partnerShare = $order->subtotal * 0.80;
                }
            }

            // Never deduct more than the partner actually received in cash
            $platformDeduction = max(0, (float) $order->total_amount - $partnerShare);

            if ($platformDeduction > 0) {
                $this->recordPASCommissionDeduction(
                    $pasReceipt->booking_id,
                    $partnerId,
                    $platformDeduction,
                    [
                        'pas_receipt_amount' => $pasReceipt->amount,
                        'commission_rate' => $order->subtotal > 0 ? round(($platformDeduction / $order->subtotal) * 100, 2) : 0,
                        'deducted_at_payout' => true,
                        'commission_rule_used' => $ruleId,
                        'partner_pct' => $snapshot['partner_pct'] ?? null,
                        'subtotal' => $snapshot['subtotal'] ?? (float) $order->subtotal,
                    ]
                );

                $totalCommissionDeducted += $platformDeduction;
            }
        }

        return $totalCommissionDeducted;
    }

    /**
     * Record the WITHDRAWAL + PLATFORM_FEE ledger entries for a payout and
     * update the partner's cached balance. Idempotent per payout (guarded by
     * the "Payout #{id}" narration, since booking_id is NULL for these rows).
     *
     * Deduction model:
     *   - WITHDRAWAL entry = net_amount (what the partner actually receives)
     *   - PLATFORM_FEE entry = platform_fee (the platform's cut for processing)
     *   - PartnerBalance.available_balance -= gross amount
     *   - PartnerBalance.total_withdrawn += net_amount
     *
     * @return bool true if the deduction was recorded now, false if already recorded
     */
    public function recordPayoutWithdrawal(PartnerPayout $payout, string $narrationSuffix = ''): bool
    {
        $this->lockUserChain($payout->partner_id);

        if ($this->hasWithdrawalEntry($payout->partner_id, (int) $payout->payout_id)) {
            return false;
        }

        $partnerLastBalance = LedgerEntry::where('user_id', $payout->partner_id)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->value('balance_after') ?? 0;

        $netAmount = (float) $payout->net_amount;
        $grossAmount = (float) $payout->amount;
        $feeAmount = max(0, $grossAmount - $netAmount);

        // WITHDRAWAL entry: the net amount actually transferred to the partner
        $this->createLedgerEntry([
            'user_id' => $payout->partner_id,
            'booking_id' => null,
            'amount' => $netAmount,
            'balance_after' => $partnerLastBalance - $netAmount,
            'type' => 'WITHDRAWAL',
            'narration' => "Payout #{$payout->payout_id} - {$payout->payment_method} - Net NPR " .
                number_format($netAmount, 2) . " (platform fee NPR " . number_format($feeAmount, 2) . ") - Ref: {$payout->transaction_reference}{$narrationSuffix}",
            'metadata' => ['payout_id' => $payout->payout_id],
        ]);

        // PLATFORM_FEE entry: the 2% processing fee is ledgered as platform revenue
        if ($feeAmount > 0) {
            $this->createLedgerEntry([
                'user_id' => $payout->partner_id,
                'booking_id' => null,
                'amount' => $feeAmount,
                'balance_after' => $partnerLastBalance - $grossAmount,
                'type' => 'PLATFORM_FEE',
                'narration' => "Payout #{$payout->payout_id} - platform processing fee NPR " . number_format($feeAmount, 2),
                'metadata' => ['payout_id' => $payout->payout_id],
            ]);
        }

        // PREPAID MODEL: partner loses the gross amount; total_withdrawn tracks the net
        $partnerBalance = PartnerBalance::where('partner_id', $payout->partner_id)->first();
        if ($partnerBalance) {
            $partnerBalance->update([
                'available_balance' => $partnerBalance->available_balance - $grossAmount,
                'total_withdrawn' => $partnerBalance->total_withdrawn + $netAmount,
                'last_updated' => now()
            ]);
        }

        return true;
    }

    /**
     * Finalize a payout as completed: record the withdrawal (if not already
     * recorded), mark the payout completed, append a WITHDRAWAL_COMPLETED
     * marker entry and notify/audit. Safe to call from admin confirmation,
     * the eSewa redirect callback and gateway webhooks.
     *
     * @return bool true if the payout was finalized now, false if it was already completed
     */
    public function finalizePayout(
        int $payoutId,
        array $options = []
    ): bool {
        $source = $options['source'] ?? 'admin';
        $actorId = $options['completed_by'] ?? null;
        $gatewayPayoutId = $options['gateway_payout_id'] ?? null;
        $gatewayFee = $options['final_gateway_fee'] ?? null;

        DB::beginTransaction();
        try {
            $payout = PartnerPayout::where('payout_id', $payoutId)->lockForUpdate()->first();
            if (!$payout) {
                DB::rollBack();
                throw new \Exception('Payout not found');
            }

            if ($payout->status === 'completed') {
                DB::rollBack();
                return false;
            }

            if (!in_array($payout->status, ['pending', 'processing'])) {
                DB::rollBack();
                throw new \Exception("Cannot complete payout in status: {$payout->status}");
            }

            $this->recordPayoutWithdrawal($payout, " - Completed via {$source}");

            $extraMetadata = $options['metadata'] ?? [];

            $payout->update([
                'status' => 'completed',
                'gateway_payout_id' => $gatewayPayoutId,
                'gateway_fee' => $gatewayFee ?? $payout->gateway_fee,
                'completed_at' => now(),
                'metadata' => array_merge($payout->metadata ?? [], $extraMetadata, [
                    'completed_via' => $source,
                    'completed_by' => $actorId,
                    'completed_at' => now()->toISOString(),
                    'gateway_payout_id' => $gatewayPayoutId,
                    'final_gateway_fee' => $gatewayFee ?? $payout->gateway_fee,
                ])
            ]);

            // Marker entry for the admin ledger trail (zero amount, no balance change)
            $partnerLastBalance = LedgerEntry::where('user_id', $payout->partner_id)
                ->orderByDesc('created_at')
                ->orderByDesc('id')
                ->value('balance_after') ?? 0;

            $this->createLedgerEntry([
                'user_id' => $payout->partner_id,
                'booking_id' => null,
                'amount' => 0,
                'balance_after' => $partnerLastBalance,
                'type' => 'WITHDRAWAL_COMPLETED',
                'narration' => "Payout #{$payout->payout_id} completed - {$source}" . ($gatewayPayoutId ? " - Gateway ID: {$gatewayPayoutId}" : ''),
                'metadata' => ['payout_id' => $payout->payout_id, 'completed_by' => $actorId, 'source' => $source],
            ]);

            DB::commit();

            Log::info('Payout completed', [
                'payout_id' => $payout->payout_id,
                'partner_id' => $payout->partner_id,
                'amount' => $payout->amount,
                'net_amount' => $payout->net_amount,
                'source' => $source,
            ]);

            \App\Services\AuditService::record($actorId, 'payout_completed', 'payout', $payout->payout_id, [
                'status' => $payout->getOriginal('status'),
            ], [
                'status' => 'completed',
                'amount' => $payout->amount,
                'net_amount' => $payout->net_amount,
                'gateway_payout_id' => $gatewayPayoutId,
                'source' => $source,
            ]);

            \App\Services\NotificationService::sendWithPush(
                $payout->partner_id,
                'payout_completed',
                'Payout Completed',
                "Your payout of NPR " . number_format((float) $payout->amount, 2) . " has been completed.",
                ['payout_id' => $payout->payout_id, 'amount' => $payout->amount]
            );

            return true;
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Whether the deduction for one specific payout is already in the ledger.
     *
     * Matches the structured payout_id (with an exact narration prefix for
     * legacy rows). A plain "%Payout #2%" LIKE also matched "Payout #20", so a
     * payout could be treated as already deducted - or have another payout's
     * amount restored when it failed.
     */
    private function hasWithdrawalEntry(int $partnerId, int $payoutId): bool
    {
        return LedgerEntry::where('user_id', $partnerId)
            ->where('type', 'WITHDRAWAL')
            ->where(function ($query) use ($payoutId) {
                $query->where('metadata->payout_id', $payoutId)
                    ->orWhere('narration', 'like', "Payout #{$payoutId} -%");
            })
            ->exists();
    }

    /**
     * Mark a payout as failed and restore the partner's balance if the money
     * was already deducted (manual processing or webhook-first completion).
     * Safe to call from admin confirmation, the eSewa failure redirect and
     * gateway webhooks. Ignores payouts already completed or cancelled.
     */
    public function failPayout(
        int $payoutId,
        string $reason,
        string $source = 'admin',
        ?int $actorId = null
    ): bool {
        DB::beginTransaction();
        try {
            $payout = PartnerPayout::where('payout_id', $payoutId)->lockForUpdate()->first();
            if (!$payout) {
                DB::rollBack();
                throw new \Exception('Payout not found');
            }

            if (in_array($payout->status, ['completed', 'cancelled'])) {
                DB::rollBack();
                Log::info('Payout failure ignored - payout is already terminal', [
                    'payout_id' => $payout->payout_id,
                    'status' => $payout->status,
                ]);
                return false;
            }

            if ($payout->status === 'failed') {
                DB::rollBack();
                return false;
            }

            // The money is only deducted from the ledger when the WITHDRAWAL
            // row exists (recordPayoutWithdrawal). processing_started_at is
            // set BEFORE any deduction in the eSewa flow (mark-processing vs
            // finalize), so it must not be used as evidence of a deduction -
            // restoring the balance on that flag alone would double-credit
            // the partner.
            $wasDeducted = $this->hasWithdrawalEntry($payout->partner_id, (int) $payout->payout_id);

            $payout->update([
                'status' => 'failed',
                'failure_reason' => $reason,
                'completed_at' => now(),
                'metadata' => array_merge($payout->metadata ?? [], [
                    'failed_via' => $source,
                    'failed_by' => $actorId,
                    'failed_at' => now()->toISOString(),
                    'failure_reason' => $reason,
                ])
            ]);

            if ($wasDeducted) {
                // Restore the gross amount to the available balance
                $partnerBalance = PartnerBalance::where('partner_id', $payout->partner_id)->first();
                if ($partnerBalance) {
                    $partnerBalance->update([
                        'available_balance' => $partnerBalance->available_balance + (float) $payout->amount,
                        'total_withdrawn' => max(0, $partnerBalance->total_withdrawn - (float) $payout->net_amount),
                        'last_updated' => now()
                    ]);
                }

                // Reverse the WITHDRAWAL and PLATFORM_FEE deductions in the chain
                $partnerLastBalance = LedgerEntry::where('user_id', $payout->partner_id)
                    ->orderByDesc('created_at')
                    ->orderByDesc('id')
                    ->value('balance_after') ?? 0;

                $this->createLedgerEntry([
                    'user_id' => $payout->partner_id,
                    'booking_id' => null,
                    'amount' => (float) $payout->amount,
                    'balance_after' => $partnerLastBalance + (float) $payout->amount,
                    'type' => 'WITHDRAWAL_REVERSAL',
                    'narration' => "Payout #{$payout->payout_id} failed - amount reversed back to available balance. Reason: {$reason}",
                    'metadata' => ['payout_id' => $payout->payout_id, 'source' => $source],
                ]);
            }

            DB::commit();

            Log::warning('Payout failed', [
                'payout_id' => $payout->payout_id,
                'partner_id' => $payout->partner_id,
                'amount' => $payout->amount,
                'reason' => $reason,
                'source' => $source,
                'balance_restored' => $wasDeducted,
            ]);

            \App\Services\AuditService::record($actorId, 'payout_failed', 'payout', $payout->payout_id, [
                'status' => 'pending|processing',
            ], [
                'status' => 'failed',
                'amount' => $payout->amount,
                'reason' => $reason,
                'source' => $source,
                'balance_restored' => $wasDeducted,
            ]);

            \App\Services\NotificationService::sendWithPush(
                $payout->partner_id,
                'payout_failed',
                'Payout Failed',
                "Your payout of NPR " . number_format((float) $payout->amount, 2) . " could not be completed. Reason: {$reason}",
                ['payout_id' => $payout->payout_id, 'amount' => $payout->amount]
            );

            return true;
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Record the promo discount the platform funded on an order.
     *
     * A discount is not simply "less cash": it is money the platform spends to
     * buy the booking, so it gets its own row. One DISCOUNT_EXPENSE row per
     * order, queryable by order / promo code / month, and reversible like any
     * other leg.
     */
    public function recordDiscountExpense(\App\Models\Order $order): bool
    {
        $discount = round((float) ($order->discount_amount ?? 0), 2);

        if ($discount <= 0) {
            return false;
        }

        $platformUserId = (int) config('payment.platform_user_id', 1);

        DB::beginTransaction();
        try {
            $this->lockUserChain($platformUserId);

            $alreadyPosted = LedgerEntry::where('booking_id', $order->order_id)
                ->where('type', 'DISCOUNT_EXPENSE')
                ->whereRaw(\App\Support\SchemaCompat::jsonFlagExpression('metadata', 'reversed') . " <> 'true'")
                ->exists();

            if ($alreadyPosted) {
                DB::rollBack();

                return false;
            }

            $currentBalance = (float) (LedgerEntry::where('user_id', $platformUserId)
                ->orderByDesc('created_at')
                ->orderByDesc('id')
                ->value('balance_after') ?? 0);

            $this->createLedgerEntry([
                'booking_id' => $order->order_id,
                'user_id' => $platformUserId,
                'type' => 'DISCOUNT_EXPENSE',
                'amount' => $discount,
                'balance_after' => $currentBalance + LedgerEntry::signedAmount('DISCOUNT_EXPENSE', $discount),
                'narration' => 'PROMO DISCOUNT: platform funded NPR ' . number_format($discount, 2)
                    . " on order #{$order->order_id}"
                    . ($order->promo_code ? " (code {$order->promo_code})" : '')
                    . '. The partner is paid on the gross service value.',
                'metadata' => [
                    'promo_code' => $order->promo_code,
                    'subtotal' => (float) $order->subtotal,
                    'total_amount' => (float) $order->total_amount,
                ],
            ]);

            DB::commit();

            return true;
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('Failed to record discount expense', [
                'order_id' => $order->order_id,
                'discount' => $discount,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Record one partner's side of a PAS job settled in cash.
     *
     *   PARTNER_EARNING      + share of the GROSS service value (the platform
     *                          funds promos, so a discount is never taken from
     *                          the partner)
     *   PAS_CASH_SETTLEMENT  - the cash the partner is holding for us
     *
     * Their sum is what the partner owes (negative) or is owed (positive); a
     * fully discounted job therefore leaves a positive balance payable as a
     * normal payout instead of a debt.
     */
    public function recordPasPartnerSettlement(
        \App\Models\Order $order,
        int $partnerId,
        float $grossServiceValue,
        float $cashCollected,
        float $partnerEarning,
        ?int $serviceId = null
    ): void {
        DB::beginTransaction();
        try {
            $this->lockUserChain($partnerId);

            $alreadySettled = LedgerEntry::where('booking_id', $order->order_id)
                ->where('user_id', $partnerId)
                ->whereIn('type', ['PARTNER_EARNING', 'PAS_RECEIPT'])
                ->exists();

            if ($alreadySettled) {
                DB::rollBack();
                Log::warning('Duplicate PAS partner settlement prevented', [
                    'order_id' => $order->order_id,
                    'partner_id' => $partnerId,
                ]);

                return;
            }

            $currentBalance = (float) (LedgerEntry::where('user_id', $partnerId)
                ->orderByDesc('created_at')
                ->orderByDesc('id')
                ->value('balance_after') ?? 0);

            if ($partnerEarning > 0) {
                $this->createLedgerEntry([
                    'booking_id' => $order->order_id,
                    'user_id' => $partnerId,
                    'type' => 'PARTNER_EARNING',
                    'amount' => $partnerEarning,
                    'balance_after' => $currentBalance + $partnerEarning,
                    'narration' => "PARTNER EARNING (cash job): order #{$order->order_id}. Gross service value NPR "
                        . number_format($grossServiceValue, 2) . ', partner share NPR ' . number_format($partnerEarning, 2)
                        . ', customer paid NPR ' . number_format($cashCollected, 2)
                        . ' in cash. Any promo discount is funded by the platform.',
                    'metadata' => [
                        'payment' => 'pas',
                        'gross_service_value' => $grossServiceValue,
                        'cash_collected' => $cashCollected,
                        'service_id' => $serviceId,
                    ],
                ]);

                $this->updatePartnerBalance($partnerId, $partnerEarning, $partnerEarning, 0);
                $currentBalance += $partnerEarning;
            }

            if ($cashCollected > 0) {
                $this->createLedgerEntry([
                    'booking_id' => $order->order_id,
                    'user_id' => $partnerId,
                    'type' => 'PAS_CASH_SETTLEMENT',
                    'amount' => -$cashCollected,
                    'balance_after' => $currentBalance - $cashCollected,
                    'narration' => "PAS CASH SETTLEMENT: order #{$order->order_id} - NPR "
                        . number_format($cashCollected, 2) . ' collected from the customer in cash. Net position after the earning: NPR '
                        . number_format($partnerEarning - $cashCollected, 2) . ' (negative = owed to the platform).',
                    'metadata' => [
                        'payment' => 'pas',
                        'cash_collected' => $cashCollected,
                        'partner_earning' => $partnerEarning,
                    ],
                ]);

                $this->updatePartnerBalance($partnerId, -$cashCollected, 0, 0);
            }

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Platform side of a PAS cash order: fee, tax and the funded discount.
     *
     *   total_amount = partner_earnings + platform_fee + tax - discount
     *
     * The fee is measured on the GROSS service value (as is the partner's
     * share), so the discount is visible as its own expense instead of quietly
     * shrinking revenue.
     */
    public function recordPasPlatformLegs(\App\Models\Order $order, float $partnerEarnings): void
    {
        $platformUserId = (int) config('payment.platform_user_id', 1);
        $gross = round((float) $order->subtotal, 2);
        $tax = round((float) ($order->tax_amount ?? 0), 2);
        $platformFee = round($gross - $partnerEarnings, 2);

        DB::beginTransaction();
        try {
            $this->lockUserChain($platformUserId);

            $currentBalance = (float) (LedgerEntry::where('user_id', $platformUserId)
                ->orderByDesc('created_at')
                ->orderByDesc('id')
                ->value('balance_after') ?? 0);

            $legs = array_filter([
                'PLATFORM_FEE' => $platformFee,
                'TAX_PAYABLE' => $tax,
            ], fn ($amount) => $amount > 0);

            foreach ($legs as $type => $amount) {
                $exists = LedgerEntry::where('booking_id', $order->order_id)
                    ->where('type', $type)
                    ->exists();

                if ($exists) {
                    continue;
                }

                $currentBalance += LedgerEntry::signedAmount($type, (float) $amount);

                $this->createLedgerEntry([
                    'booking_id' => $order->order_id,
                    'user_id' => $platformUserId,
                    'type' => $type,
                    'amount' => $amount,
                    'balance_after' => $currentBalance,
                    'narration' => $type === 'PLATFORM_FEE'
                        ? "PLATFORM FEE (cash job): order #{$order->order_id} - gross service value NPR "
                            . number_format($gross, 2) . ', partner earnings NPR ' . number_format($partnerEarnings, 2) . '.'
                        : "TAX PAYABLE (cash job): order #{$order->order_id} - VAT collected in cash from the customer.",
                    'metadata' => ['payment' => 'pas', 'gross_service_value' => $gross, 'partner_earnings' => $partnerEarnings],
                ]);
            }

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }

        // Shared with the online path so every discounted order has one row.
        $this->recordDiscountExpense($order);
    }

    /**
     * Helper: Create ledger entry
     */
    private function createLedgerEntry(array $data): LedgerEntry
    {
        return LedgerEntry::create([
            'booking_id' => $data['booking_id'],
            'user_id' => $data['user_id'],
            'type' => $data['type'],
            'amount' => $data['amount'],
            'currency' => 'NPR',
            'balance_after' => $data['balance_after'],
            'narration' => $data['narration'],
            'metadata' => $data['metadata'] ?? null,
            'created_at' => now()
        ]);
    }

    /**
     * Helper: Get last balance for a booking
     */
    private function getLastBalance(string $bookingId): float
    {
        $lastEntry = LedgerEntry::where('booking_id', $bookingId)
            ->orderBy('created_at', 'desc')
            ->orderBy('id', 'desc')
            ->first();

        return $lastEntry ? (float) $lastEntry->balance_after : 0;
    }

    /**
     * Helper: Update partner balance (PREPAID MODEL)
     */
    private function updatePartnerBalance(int $partnerId, float $availableChange, float $earnedChange = 0, float $withdrawnChange = 0): void
    {
        $balance = PartnerBalance::firstOrCreate(
            ['partner_id' => $partnerId],
            [
                'available_balance' => 0,
                'total_earnings' => 0,    // Fixed field name
                'total_withdrawn' => 0,
                'last_updated' => now()
            ]
        );

        $balance->update([
            'available_balance' => $balance->available_balance + $availableChange,
            'total_earnings' => $balance->total_earnings + $earnedChange,    // Fixed field name
            'total_withdrawn' => $balance->total_withdrawn + $withdrawnChange,
            'last_updated' => now()
        ]);
    }
}
