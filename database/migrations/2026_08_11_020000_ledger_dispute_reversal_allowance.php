<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Allow PAS dispute reversals in the ledger.
 *
 * The old unique constraint UNIQUE(booking_id, user_id, type) prevented
 * double-posting, but it also blocks the opposite-sign reversal entries
 * posted by LedgerService::reversePASCashSettlement (same booking, user
 * and type - only the amount and the metadata flag differ).
 *
 * The constraint is replaced by a partial unique index that exempts rows
 * marked metadata->dispute_reversal = true. Double-post protection for
 * normal entries is preserved, while dispute reversals can coexist.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE ledger_entries DROP CONSTRAINT IF EXISTS ledger_booking_user_type_unique');
        DB::statement(
            "CREATE UNIQUE INDEX ledger_booking_user_type_nonreversal_uniq
             ON ledger_entries (booking_id, user_id, type)
             WHERE (metadata->>'dispute_reversal') IS DISTINCT FROM 'true'"
        );
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS ledger_booking_user_type_nonreversal_uniq');
        DB::statement(
            'ALTER TABLE ledger_entries ADD CONSTRAINT ledger_booking_user_type_unique UNIQUE (booking_id, user_id, type)'
        );
    }
};
