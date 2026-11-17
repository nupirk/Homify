<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PartnerBalance extends Model
{
    protected $table = 'partner_balances';
    protected $primaryKey = 'balance_id';
    public $incrementing = true;
    protected $keyType = 'int';

    protected $fillable = [
        'partner_id',
        'available_balance',
        'total_earnings',    // PREPAID: Renamed from total_earned
        'total_withdrawn',
        'last_updated',
    ];

    protected $casts = [
        'available_balance' => 'decimal:2',
        'total_earnings' => 'decimal:2',     // PREPAID: Renamed from total_earned
        'total_withdrawn' => 'decimal:2',
        'last_updated' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    // Relationships
    public function partner(): BelongsTo
    {
        return $this->belongsTo(PartnerProfile::class, 'partner_id', 'partner_id');
    }

    public function earnings(): HasMany
    {
        return $this->hasMany(LedgerEntry::class, 'user_id', 'partner_id')
            ->where('type', 'PARTNER_EARNING');
    }

    // Helper methods for PREPAID model
    public function getTotalBalanceAttribute(): float
    {
        return $this->available_balance; // PREPAID: Only available_balance matters
    }

    // Static methods
    public static function getOrCreateForPartner(int $partnerId): self
    {
        return self::firstOrCreate(
            ['partner_id' => $partnerId],
            [
                'available_balance' => 0,
                'total_earnings' => 0,      // PREPAID: Use total_earnings
                'total_withdrawn' => 0,
                'last_updated' => now(),
            ]
        );
    }

    /**
     * Recompute the cached balance for a partner straight from the ledger chain.
     * Formula (mirrors LedgerController::reconcileSinglePartner):
     *   earnings     = PARTNER_EARNING + COD_RECEIPT - COD_COMMISSION - EARNING_REVERSAL
     *   withdrawals  = WITHDRAWAL + WITHDRAWAL_REQUEST - WITHDRAWAL_REVERSAL
     *   available    = earnings - withdrawals - pending payout requests
     */
    public static function recalculateForPartner(int $partnerId): self
    {
        $balance = self::getOrCreateForPartner($partnerId);

        $totalEarnings = LedgerEntry::where('user_id', $partnerId)
                ->where('type', 'PARTNER_EARNING')
                ->sum('amount')
            + LedgerEntry::where('user_id', $partnerId)
                ->where('type', 'PAS_RECEIPT')
                ->sum('amount')
            - LedgerEntry::where('user_id', $partnerId)
                ->where('type', 'PAS_COMMISSION')
                ->sum('amount')
            - LedgerEntry::where('user_id', $partnerId)
                ->where('type', 'EARNING_REVERSAL')
                ->sum('amount');

        $totalWithdrawn = LedgerEntry::where('user_id', $partnerId)
                ->where('type', 'WITHDRAWAL')
                ->sum('amount')
            - LedgerEntry::where('user_id', $partnerId)
                ->where('type', 'WITHDRAWAL_REVERSAL')
                ->sum('amount');

        $pending = PartnerPayout::where('partner_id', $partnerId)
            ->whereIn('status', ['pending', 'processing'])
            ->sum('amount');

        // MODEL A: PAS cash settlements remove the cash-collected share from
        // the available balance (the partner kept the cash). They are excluded
        // from earnings above and subtracted from available below.
        $pasCashSettlements = LedgerEntry::where('user_id', $partnerId)
            ->where('type', 'PAS_CASH_SETTLEMENT')
            ->sum('amount');

        // Payout processing fees (posted as PLATFORM_FEE against the partner
        // when a payout is deducted) reduce the available balance at runtime;
        // subtract them here too so a recalc matches the runtime chain.
        $payoutFees = LedgerEntry::where('user_id', $partnerId)
            ->where('type', 'PLATFORM_FEE')
            ->sum('amount');

        $available = $totalEarnings - $totalWithdrawn - $pending + $pasCashSettlements - $payoutFees;

        $balance->update([
            'available_balance' => $available,
            'total_earnings' => $totalEarnings,
            'total_withdrawn' => $totalWithdrawn,
            'last_updated' => now(),
        ]);

        return $balance;
    }
}
