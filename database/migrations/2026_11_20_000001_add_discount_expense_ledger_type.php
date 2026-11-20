<?php

use App\Support\SchemaCompat;
use Illuminate\Database\Migrations\Migration;

/**
 * A promo discount is money the platform spends to buy a booking, so it needs
 * a ledger row of its own instead of only showing up as "less cash than
 * expected". SQLite stores the type as plain text, so only PostgreSQL needs the
 * enum value and the check constraint rebuilt.
 */
return new class extends Migration
{
    private const TYPES = [
        'CUSTOMER_DEBIT',
        'ESCROW_CREDIT',
        'PARTNER_EARNING',
        'PLATFORM_FEE',
        'TAX_PAYABLE',
        'WITHDRAWAL',
        'REFUND',
        'PAS_RECEIPT',
        'PAS_COMMISSION',
        'PAS_CASH_SETTLEMENT',
        'WITHDRAWAL_COMPLETED',
        'BALANCE_RECONCILIATION',
        'WITHDRAWAL_REVERSAL',
        'EARNING_REVERSAL',
        'DISCOUNT_EXPENSE',
    ];

    public function up(): void
    {
        SchemaCompat::statement(
            "DO $$ BEGIN ALTER TYPE ledger_entry_type ADD VALUE IF NOT EXISTS 'DISCOUNT_EXPENSE'; EXCEPTION WHEN undefined_object THEN NULL; END $$;"
        );

        $this->rebuildTypeConstraint(self::TYPES);
    }

    public function down(): void
    {
        // PostgreSQL cannot drop a single enum value; the reversal keeps the
        // value and only removes the rows (there should be none on a rollback
        // of an unfinished deploy).
        SchemaCompat::statement("DELETE FROM ledger_entries WHERE type = 'DISCOUNT_EXPENSE'");

        $this->rebuildTypeConstraint(array_values(array_diff(self::TYPES, ['DISCOUNT_EXPENSE'])));
    }

    private function rebuildTypeConstraint(array $types): void
    {
        $values = collect($types)->map(fn ($value) => "'{$value}'::text")->implode(', ');

        SchemaCompat::statement('ALTER TABLE ledger_entries DROP CONSTRAINT IF EXISTS ledger_entries_type_check');
        SchemaCompat::statement(
            "ALTER TABLE ledger_entries ADD CONSTRAINT ledger_entries_type_check CHECK ((type)::text = ANY (ARRAY[{$values}]))"
        );
    }
};
