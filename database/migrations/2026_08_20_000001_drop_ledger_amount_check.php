<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Drop the amount >= 0 CHECK on ledger_entries.
 *
 * The ledger deliberately stores signed amounts: PAS cash settlements
 * (LedgerService) and dispute reversals post NEGATIVE amounts
 * (PAS_CASH_SETTLEMENT, reversed PAS_RECEIPT) to remove money from a
 * partner's available balance. The check was only ever present in the
 * migration file - it was added after the migration had already run, so no
 * deployed database actually has it. A fresh migrate:fresh WOULD create it
 * and then every PAS cash settlement would crash. Drop it explicitly so
 * fresh and existing databases behave identically.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE ledger_entries DROP CONSTRAINT IF EXISTS ledger_entries_amount_check');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE ledger_entries ADD CONSTRAINT ledger_entries_amount_check CHECK (amount >= 0)');
    }
};