<?php

use Illuminate\Database\Migrations\Migration;
use App\Support\SchemaCompat;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Model A (COD cash settlement): a partner who collects COD cash keeps it.
     * Their share is settled on the spot, so the ledger records the full amount
     * as a COD_RECEIPT (earnings history) followed by a COD_CASH_SETTLEMENT that
     * removes the cash-collected share from the available balance. Only the
     * platform commission remains as a deduction (money the partner owes).
     */
    public function up(): void
    {
        SchemaCompat::statement("ALTER TABLE ledger_entries DROP CONSTRAINT IF EXISTS ledger_entries_type_check");
        SchemaCompat::statement("ALTER TABLE ledger_entries ADD CONSTRAINT ledger_entries_type_check CHECK ((type)::text = ANY (ARRAY['CUSTOMER_DEBIT'::text, 'ESCROW_CREDIT'::text, 'PARTNER_EARNING'::text, 'PLATFORM_FEE'::text, 'TAX_PAYABLE'::text, 'WITHDRAWAL'::text, 'REFUND'::text, 'COD_RECEIPT'::text, 'COD_COMMISSION'::text, 'COD_CASH_SETTLEMENT'::text, 'WITHDRAWAL_COMPLETED'::text, 'BALANCE_RECONCILIATION'::text, 'WITHDRAWAL_REVERSAL'::text, 'EARNING_REVERSAL'::text]))");
    }

    public function down(): void
    {
        SchemaCompat::statement("ALTER TABLE ledger_entries DROP CONSTRAINT IF EXISTS ledger_entries_type_check");
        SchemaCompat::statement("ALTER TABLE ledger_entries ADD CONSTRAINT ledger_entries_type_check CHECK ((type)::text = ANY (ARRAY['CUSTOMER_DEBIT'::text, 'ESCROW_CREDIT'::text, 'PARTNER_EARNING'::text, 'PLATFORM_FEE'::text, 'TAX_PAYABLE'::text, 'WITHDRAWAL'::text, 'REFUND'::text, 'COD_RECEIPT'::text, 'COD_COMMISSION'::text, 'WITHDRAWAL_COMPLETED'::text, 'BALANCE_RECONCILIATION'::text, 'WITHDRAWAL_REVERSAL'::text, 'EARNING_REVERSAL'::text]))");
    }
};
