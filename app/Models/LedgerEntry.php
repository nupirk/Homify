<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LedgerEntry extends Model
{
    use HasFactory;

    protected $table = 'ledger_entries';
    protected $dateFormat = 'Y-m-d H:i:s.u';

    // We manage created_at manually but let Laravel handle timestamps
    const UPDATED_AT = null; // We don't use updated_at

    protected $fillable = [
        'booking_id',
        'user_id',
        'type',
        'amount',
        'currency',
        'balance_after',
        'narration',
        'metadata',
        'created_at'
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'balance_after' => 'decimal:2',
        'metadata' => 'array',
        'created_at' => 'datetime',
    ];

    /**
     * Types whose stored amount leaves the user's wallet (negative
     * contribution). Every other type contributes +amount. This is the
     * single source of truth - writers computing balance_after and readers
     * summing balances must both go through signedAmount().
     */
    public const DEBIT_TYPES = [
        'CUSTOMER_DEBIT',
        'WITHDRAWAL',
        'PAS_COMMISSION',
        'TAX_PAYABLE',
        'PLATFORM_FEE',
        'EARNING_REVERSAL',
        'DISCOUNT_EXPENSE',
    ];

    /**
     * Contribution of a ledger row to its user's balance under the shared
     * sign convention (see getUserBalance).
     */
    public static function signedAmount(string $type, float $amount): float
    {
        return in_array($type, self::DEBIT_TYPES, true) ? -$amount : $amount;
    }

    // Relationship to order/booking
    public function order()
    {
        return $this->belongsTo(Order::class, 'booking_id', 'order_id');
    }

    // Relationship to user
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'user_id');
    }

    // Helper method to calculate user balance
    public static function getUserBalance($userId): float
    {
        // Sign convention: money OUT of the user's wallet is negative
        // (CUSTOMER_DEBIT - customer pays, WITHDRAWAL - partner cashes out,
        // PAS_COMMISSION / EARNING_REVERSAL - platform takes its share back,
        // TAX_PAYABLE / PLATFORM_FEE - posted against the party who pays them:
        // a partner's payout fee is PLATFORM_FEE on THEIR row, so it is a
        // debit for them; the platform's own revenue rows are ESCROW_CREDIT.
        // Money INTO the wallet is positive (ESCROW_CREDIT, PARTNER_EARNING,
        // PAS_RECEIPT, WITHDRAWAL_REVERSAL, REFUND - money returned to the
        // customer).
        // PAS_CASH_SETTLEMENT is POSTED NEGATIVE (the partner kept the cash),
        // so it lands in the ELSE branch and correctly reduces the balance.
        return (float) self::where('user_id', $userId)
            ->selectRaw('
                COALESCE(SUM(
                    CASE WHEN type IN (' . implode(',', array_map(fn ($t) => "'{$t}'", self::DEBIT_TYPES)) . ')
                    THEN -amount
                    ELSE amount END
                ), 0) as balance
            ')
            ->value('balance');
    }
    // Get the balance_after from the latest ledger entry
    public static function getLastBalance(): float
    {
        /*  $lastRow = LedgerEntry::latest('id')->first(); // Order by ID instead of created_at */
        $lastRow = LedgerEntry::orderByDesc('created_at')->first();

        return $lastRow ? (float)$lastRow->balance_after : 0.0;
    }
    // Create multiple ledger entries with balance calculation
    public static function createMany(array $entries): void
    {
        foreach ($entries as $entry) {
            // Calculate running balance for this user (per-user, not global)
            $currentBalance = (float) self::where('user_id', $entry['user_id'])
                ->orderByDesc('created_at')
                ->orderByDesc('id')
                ->value('balance_after') ?? 0;

            // Debits reduce the balance, credits increase it
            $isDebit = in_array($entry['type'], ['WITHDRAWAL', 'REFUND', 'TAX_PAYABLE', 'PLATFORM_FEE', 'PAS_COMMISSION', 'PAS_CASH_SETTLEMENT', 'CUSTOMER_DEBIT']);
            $isCredit = in_array($entry['type'], ['ESCROW_CREDIT', 'PAS_RECEIPT', 'PARTNER_EARNING', 'WITHDRAWAL_REVERSAL']);
            $balanceChange = $isCredit ? $entry['amount'] : ($isDebit ? -$entry['amount'] : 0);
            $newBalance = $currentBalance + $balanceChange;

            // Create the entry with balance_after and timestamp
            self::create(array_merge($entry, [
                'balance_after' => $newBalance,
                'currency' => $entry['currency'] ?? 'NPR',
                /* 'created_at' => \Carbon\Carbon::now('Asia/Kathmandu')->format('Y-m-d H:i:s.u') */
                'created_at'   => \Carbon\Carbon::now('Asia/Kathmandu')->format('Y-m-d H:i:s.u'),
            ]));
        }
    }

    // Check if entries exist for a booking (idempotency)
    public static function bookingEntriesExist($bookingId): bool
    {
        return self::where('booking_id', $bookingId)->exists();
    }
}
