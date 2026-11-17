<?php

namespace App\Console\Commands;

use App\Models\LedgerEntry;
use App\Support\SchemaCompat;
use App\Models\Order;
use App\Services\LedgerService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Backfill for the current PAS flow: ledger entries (PAS_RECEIPT +
 * PAS_CASH_SETTLEMENT + PAS_COMMISSION) used to be posted only when the
 * customer confirmed the cash handover. Customers rarely confirm, so
 * completed cash jobs ended up with zero entries in the partner's ledger.
 *
 * This command posts the missing entries for every completed PAS cash
 * order that has no receipt yet. Idempotent - recordPASReceipt guards
 * against duplicates, and dispute reversals are excluded.
 */
class BackfillPASCashCompletions extends Command
{
    protected $signature = 'ledger:backfill-pas-cash-completions
        {--dry-run : Preview changes without writing}
        {--partner= : Only process orders for this partner id}';

    protected $description = 'Post PAS_RECEIPT + settlement + commission for completed PAS cash orders missing ledger entries';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $partnerFilter = $this->option('partner');
        if ($dryRun) {
            $this->warn('DRY RUN - no changes will be written');
        }

        $query = Order::query()
            ->where('payment_method', 'pas')
            ->where('payment_received', 'cash')
            ->where('status', 'completed')
            ->where('cash_disputed', false)
            ->whereNotExists(function ($q) {
                $q->select(DB::raw(1))
                    ->from('ledger_entries')
                    ->whereColumn('ledger_entries.booking_id', 'orders.order_id')
                    ->where('ledger_entries.type', 'PAS_RECEIPT')
                    ->whereRaw(SchemaCompat::jsonFlagExpression('ledger_entries.metadata', 'dispute_reversal') . " IS DISTINCT FROM 'true'");
            });

        if ($partnerFilter) {
            $query->whereHas('serviceRequests', function ($q) use ($partnerFilter) {
                $q->where('partner_id', (int) $partnerFilter)->whereNotNull('partner_id');
            });
        }

        $orders = $query->with(['serviceRequests' => fn ($q) => $q->whereNotNull('partner_id')->limit(1)])->get();

        if ($orders->isEmpty()) {
            $this->info('No completed PAS cash orders missing ledger entries.');
            return self::SUCCESS;
        }

        $this->info("Found {$orders->count()} completed PAS cash order(s) missing ledger entries.");
        $posted = 0;

        foreach ($orders as $order) {
            $partnerRequest = $order->serviceRequests->first();
            $partnerId = $partnerRequest?->partner_id;

            if (!$partnerId) {
                $this->warn("  Order #{$order->order_id}: no partner attached, skipping.");
                continue;
            }

            $this->line("  Order #{$order->order_id} -> partner #{$partnerId}, NPR {$order->total_amount}");

            if ($dryRun) {
                continue;
            }

            try {
                app(LedgerService::class)->recordPASReceipt(
                    $partnerId,
                    $order->order_id,
                    (float) $order->total_amount,
                    ['confirmed_via' => 'partner_completion', 'confirmed_at' => $order->updated_at?->toDateTimeString() ?? now()->toDateTimeString()]
                );
                $posted++;
            } catch (\Exception $e) {
                $this->error("  Order #{$order->order_id} failed: {$e->getMessage()}");
            }
        }

        $this->newLine();
        if ($dryRun) {
            $this->warn('DRY RUN - nothing was written. Re-run without --dry-run to apply.');
        } else {
            $this->info("Backfill complete. {$posted} order(s) posted to the ledger.");
        }

        return self::SUCCESS;
    }
}
