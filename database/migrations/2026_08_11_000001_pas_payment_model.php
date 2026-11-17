<?php

use Illuminate\Database\Migrations\Migration;
use App\Support\SchemaCompat;
use Illuminate\Support\Facades\DB;

/**
 * Renames the "Cash on Delivery" (COD) payment model to "Pay After Service" (PAS).
 *
 * Semantics change: PAS no longer means "cash at the door". It means the payment
 * happens AFTER the work is done, and can be settled either online (company QR /
 * gateway, partner credited their 80% share) or in cash (partner keeps the cash,
 * balance debited the platform commission).
 *
 * Changes:
 *  - orders.payment_method enum value 'cod' -> 'pas'
 *  - ledger_entries.type values COD_RECEIPT/COD_COMMISSION/COD_CASH_SETTLEMENT
 *    -> PAS_RECEIPT/PAS_COMMISSION/PAS_CASH_SETTLEMENT (enum + check constraint)
 *  - orders gains payment_received (online|cash), cash_confirmed (+at) and
 *    cash_disputed (+at) columns used by the cash-declaration confirmation flow.
 */
return new class extends Migration
{
    /**
     * Convert a legacy varchar+CHECK enum column to a real Postgres enum.
     * Drops the Laravel-style check constraint first (it would block the value
     * remap), uses a distinct type name to avoid column/type ambiguity, and
     * re-adds a matching check constraint afterwards.
     */
    private function replaceEnumValues(
        string $table,
        string $column,
        string $typeName,
        string $checkName,
        array $newValues,
        array $mapping
    ): void {
        SchemaCompat::statement("ALTER TABLE {$table} DROP CONSTRAINT IF EXISTS {$checkName}");
        SchemaCompat::statement("ALTER TABLE {$table} ALTER COLUMN {$column} DROP DEFAULT");
        SchemaCompat::statement("ALTER TABLE {$table} ALTER COLUMN {$column} TYPE TEXT USING {$column}::text");

        foreach ($mapping as $old => $new) {
            DB::table($table)->where($column, $old)->update([$column => $new]);
        }

        SchemaCompat::statement("DROP TYPE IF EXISTS {$typeName}");
        $enumVals = collect($newValues)->map(fn ($v) => "'{$v}'")->implode(', ');
        SchemaCompat::statement("CREATE TYPE {$typeName} AS ENUM ({$enumVals})");
        SchemaCompat::statement("ALTER TABLE {$table} ALTER COLUMN {$column} TYPE {$typeName} USING {$column}::{$typeName}");
        SchemaCompat::statement("ALTER TABLE {$table} ALTER COLUMN {$column} SET DEFAULT '{$newValues[0]}'");
    }

    public function up(): void
    {
        // 1. orders.payment_method: 'cod' -> 'pas'
        $this->replaceEnumValues(
            'orders',
            'payment_method',
            'payment_method_type',
            'orders_payment_method_check',
            ['online', 'pas'],
            ['cod' => 'pas']
        );

        SchemaCompat::statement("COMMENT ON COLUMN orders.payment_method IS 'Payment method: online (prepaid) or pas (pay after service - settled online via QR or in cash after the work is done)'");

        // 2. New PAS settlement columns on orders
        SchemaCompat::statement("DO \$\$ BEGIN CREATE TYPE payment_received AS ENUM ('online', 'cash'); EXCEPTION WHEN duplicate_object THEN NULL; END \$\$;");
        SchemaCompat::statement('ALTER TABLE orders ADD COLUMN payment_received payment_received NULL');
        SchemaCompat::statement('ALTER TABLE orders ADD COLUMN cash_confirmed BOOLEAN NOT NULL DEFAULT FALSE');
        SchemaCompat::statement('ALTER TABLE orders ADD COLUMN cash_confirmed_at TIMESTAMP NULL');
        SchemaCompat::statement('ALTER TABLE orders ADD COLUMN cash_disputed BOOLEAN NOT NULL DEFAULT FALSE');
        SchemaCompat::statement('ALTER TABLE orders ADD COLUMN cash_disputed_at TIMESTAMP NULL');

        // 3. ledger_entries.type: COD_* -> PAS_*
        $newTypes = [
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
        ];

        $this->replaceEnumValues(
            'ledger_entries',
            'type',
            'ledger_entry_type',
            'ledger_entries_type_check',
            $newTypes,
            [
                'COD_RECEIPT' => 'PAS_RECEIPT',
                'COD_COMMISSION' => 'PAS_COMMISSION',
                'COD_CASH_SETTLEMENT' => 'PAS_CASH_SETTLEMENT',
            ]
        );

        // Rebuild the check constraint with the renamed types
        $checkVals = collect($newTypes)->map(fn ($v) => "'{$v}'::text")->implode(', ');
        SchemaCompat::statement("ALTER TABLE ledger_entries ADD CONSTRAINT ledger_entries_type_check CHECK ((type)::text = ANY (ARRAY[{$checkVals}]))");

        // 4. Update legacy comments referencing COD
        SchemaCompat::statement("COMMENT ON COLUMN orders.commission_deducted IS 'Whether platform commission has been deducted from partner for PAS cash-settled orders'");
        SchemaCompat::statement("COMMENT ON COLUMN orders.commission_deducted_at IS 'When commission was deducted for PAS cash-settled orders'");
    }

    public function down(): void
    {
        // 1. Drop new settlement columns
        SchemaCompat::statement('ALTER TABLE orders DROP COLUMN IF EXISTS cash_disputed_at');
        SchemaCompat::statement('ALTER TABLE orders DROP COLUMN IF EXISTS cash_disputed');
        SchemaCompat::statement('ALTER TABLE orders DROP COLUMN IF EXISTS cash_confirmed_at');
        SchemaCompat::statement('ALTER TABLE orders DROP COLUMN IF EXISTS cash_confirmed');
        SchemaCompat::statement('ALTER TABLE orders DROP COLUMN IF EXISTS payment_received');
        SchemaCompat::statement("DO \$\$ BEGIN IF EXISTS (SELECT 1 FROM pg_type WHERE typname = 'payment_received' AND typtype = 'e') THEN DROP TYPE payment_received; END IF; END \$\$;");

        // 2. ledger_entries.type: PAS_* -> COD_*
        $oldTypes = [
            'CUSTOMER_DEBIT',
            'ESCROW_CREDIT',
            'PARTNER_EARNING',
            'PLATFORM_FEE',
            'TAX_PAYABLE',
            'WITHDRAWAL',
            'REFUND',
            'COD_RECEIPT',
            'COD_COMMISSION',
            'COD_CASH_SETTLEMENT',
            'WITHDRAWAL_COMPLETED',
            'BALANCE_RECONCILIATION',
            'WITHDRAWAL_REVERSAL',
            'EARNING_REVERSAL',
        ];

        $this->replaceEnumValues(
            'ledger_entries',
            'type',
            'ledger_entry_type',
            'ledger_entries_type_check',
            $oldTypes,
            [
                'PAS_RECEIPT' => 'COD_RECEIPT',
                'PAS_COMMISSION' => 'COD_COMMISSION',
                'PAS_CASH_SETTLEMENT' => 'COD_CASH_SETTLEMENT',
            ]
        );

        $checkVals = collect($oldTypes)->map(fn ($v) => "'{$v}'::text")->implode(', ');
        SchemaCompat::statement("ALTER TABLE ledger_entries ADD CONSTRAINT ledger_entries_type_check CHECK ((type)::text = ANY (ARRAY[{$checkVals}]))");

        // 3. orders.payment_method: 'pas' -> 'cod'
        $this->replaceEnumValues(
            'orders',
            'payment_method',
            'payment_method_type',
            'orders_payment_method_check',
            ['online', 'cod'],
            ['pas' => 'cod']
        );

        SchemaCompat::statement("COMMENT ON COLUMN orders.payment_method IS 'Payment method: online (prepaid) or cod (cash on delivery)'");
    }
};
