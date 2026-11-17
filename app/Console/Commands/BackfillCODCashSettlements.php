<?php

namespace App\Console\Commands;

use App\Models\LedgerEntry;
use App\Models\PartnerBalance;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * One-off backfill for MODEL A (COD cash settlement).
 *
 * Historically, COD receipts were full-credited to the partner's available
 * balance and only the commission was deducted - the cash-collected share
 * stayed withdrawable (double payment). This command inserts a
 * PAS_CASH_SETTLEMENT (-full amount) after every PAS_RECEIPT that does not
 * have one yet (refunded jobs are skipped - their receipt was already
 * reversed), then rebuilds each affected partner's balance_after chain and
 * recalculates the cached PartnerBalance.
 */
class BackfillCODCashSettlements extends Command
{
    protected $signature = 'ledger:backfill-pas-settlements {--dry-run : Preview changes without writing}';

    protected $description = 'Insert PAS_CASH_SETTLEMENT entries for legacy COD receipts (Model A) and rebuild balance chains';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        if ($dryRun) {
            $this->warn('DRY RUN - no changes will be written');
        }

        // COD receipts without a settlement and without a refund reversal
        $receipts = DB::table('ledger_entries')
            ->select('ledger_entries.id', 'ledger_entries.booking_id', 'ledger_entries.user_id', 'ledger_entries.amount', 'ledger_entries.created_at')
            ->where('ledger_entries.type', 'PAS_RECEIPT')
            ->whereNotExists(function ($query) {
                $query->select(DB::raw(1))
                    ->from('ledger_entries as settlements')
                    ->whereRaw('settlements.booking_id = ledger_entries.booking_id')
                    ->where('settlements.user_id', '=', DB::raw('ledger_entries.user_id'))
                    ->where('settlements.type', '=', 'PAS_CASH_SETTLEMENT');
            })
            ->whereNotExists(function ($query) {
                $query->select(DB::raw(1))
                    ->from('ledger_entries as reversals')
                    ->whereRaw('reversals.booking_id = ledger_entries.booking_id')
                    ->where('reversals.user_id', '=', DB::raw('ledger_entries.user_id'))
                    ->where('reversals.type', '=', 'EARNING_REVERSAL');
            })
            ->orderBy('ledger_entries.created_at')
            ->get();

        $this->info("Found {$receipts->count()} legacy COD receipt(s) missing a cash settlement.");

        if ($receipts->isEmpty()) {
            return self::SUCCESS;
        }

        $affectedUserIds = $receipts->pluck('user_id')->unique()->values();
        $totalSettled = 0;

        foreach ($affectedUserIds as $userId) {
            $userReceipts = $receipts->where('user_id', $userId);
            $userTotal = $userReceipts->sum('amount');
            $totalSettled += $userTotal;

            $this->line("  Partner #{$userId}: {$userReceipts->count()} receipt(s), settling NPR " . number_format($userTotal, 2));

            if ($dryRun) {
                continue;
            }

            DB::beginTransaction();
            try {
                foreach ($userReceipts as $receipt) {
                    $settlementAmount = -abs((float) $receipt->amount);

                    LedgerEntry::create([
                        'booking_id' => $receipt->booking_id,
                        'user_id' => $userId,
                        'type' => 'PAS_CASH_SETTLEMENT',
                        'amount' => $settlementAmount,
                        'balance_after' => 0, // fixed by the chain rebuild below
                        'currency' => 'NPR',
                        'narration' => "COD CASH SETTLEMENT: Order #{$receipt->booking_id} - partner collected cash from customer; share settled in cash (backfill migration).",
                        'metadata' => ['backfilled' => true, 'source' => 'ledger:backfill-pas-settlements'],
                        // Keep the settlement directly after its receipt in chain order
                        'created_at' => $receipt->created_at,
                    ]);
                }

                $this->rebuildBalanceChain($userId);
                PartnerBalance::recalculateForPartner($userId);
                DB::commit();

                $this->info("  Partner #{$userId}: settled and chain rebuilt.");
            } catch (\Exception $e) {
                DB::rollBack();
                $this->error("  Partner #{$userId} failed: {$e->getMessage()}");
                continue;
            }
        }

        $this->newLine();
        $this->info('Backfill complete. Total settled: NPR ' . number_format($totalSettled, 2));
        if ($dryRun) {
            $this->warn('DRY RUN - nothing was written. Re-run without --dry-run to apply.');
        } else {
            $this->warn('Run admin > ledger > partner balance report to confirm zero inconsistencies.');
        }

        return self::SUCCESS;
    }

    /**
     * Rebuild the running balance chain for a user from the first entry.
     * The first entry's balance_after is preserved (it encodes the initial
     * balance); every later entry gets balance_after = previous + amount.
     */
    private function rebuildBalanceChain(int $userId): void
    {
        $entries = LedgerEntry::where('user_id', $userId)
            ->orderBy('created_at')
            ->orderBy('id')
            ->get(['id', 'balance_after', 'amount']);

        $running = null;
        foreach ($entries as $entry) {
            if ($running === null) {
                $running = (float) $entry->balance_after;
                continue;
            }
            $running += (float) $entry->amount;
            LedgerEntry::where('id', $entry->id)->update(['balance_after' => round($running, 2)]);
        }
    }
}
