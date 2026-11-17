<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Persist gateway payout IDs and failure reasons (previously written but silently dropped)
        DB::statement("ALTER TABLE partner_payouts ADD COLUMN gateway_payout_id VARCHAR(255) NULL");
        DB::statement("ALTER TABLE partner_payouts ADD COLUMN failure_reason TEXT NULL");

        // Unique gateway references: only one payout may reference a given gateway transaction
        DB::statement("CREATE UNIQUE INDEX partner_payouts_transaction_reference_unique ON partner_payouts (transaction_reference) WHERE transaction_reference IS NOT NULL");

        // Allow structured context (commission rule snapshots, gateway data) on ledger entries
        DB::statement("ALTER TABLE ledger_entries ADD COLUMN metadata JSONB NULL");

        // Add EARNING_REVERSAL to the allowed ledger entry types (refund clawback)
        DB::statement("ALTER TABLE ledger_entries DROP CONSTRAINT IF EXISTS ledger_entries_type_check");
        DB::statement("ALTER TABLE ledger_entries ADD CONSTRAINT ledger_entries_type_check CHECK ((type)::text = ANY (ARRAY['CUSTOMER_DEBIT'::text, 'ESCROW_CREDIT'::text, 'PARTNER_EARNING'::text, 'PLATFORM_FEE'::text, 'TAX_PAYABLE'::text, 'WITHDRAWAL'::text, 'REFUND'::text, 'COD_RECEIPT'::text, 'COD_COMMISSION'::text, 'WITHDRAWAL_COMPLETED'::text, 'BALANCE_RECONCILIATION'::text, 'WITHDRAWAL_REVERSAL'::text, 'EARNING_REVERSAL'::text]))");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement("ALTER TABLE ledger_entries DROP CONSTRAINT IF EXISTS ledger_entries_type_check");
        DB::statement("ALTER TABLE ledger_entries ADD CONSTRAINT ledger_entries_type_check CHECK ((type)::text = ANY (ARRAY['CUSTOMER_DEBIT'::text, 'ESCROW_CREDIT'::text, 'PARTNER_EARNING'::text, 'PLATFORM_FEE'::text, 'TAX_PAYABLE'::text, 'WITHDRAWAL'::text, 'REFUND'::text, 'COD_RECEIPT'::text, 'COD_COMMISSION'::text, 'WITHDRAWAL_COMPLETED'::text, 'BALANCE_RECONCILIATION'::text, 'WITHDRAWAL_REVERSAL'::text]))");

        DB::statement("ALTER TABLE ledger_entries DROP COLUMN IF EXISTS metadata");
        DB::statement("DROP INDEX IF EXISTS partner_payouts_transaction_reference_unique");
        DB::statement("ALTER TABLE partner_payouts DROP COLUMN IF EXISTS failure_reason");
        DB::statement("ALTER TABLE partner_payouts DROP COLUMN IF EXISTS gateway_payout_id");
    }
};
