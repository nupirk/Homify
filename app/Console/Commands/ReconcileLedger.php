<?php

namespace App\Console\Commands;

use App\Models\LedgerEntry;
use App\Models\PartnerBalance;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Scans the real ledger for discrepancies:
 *   1. Chain integrity  - every entry's balance_after must equal the
 *      previous entry's balance_after plus the signed amount.
 *   2. Booking conservation - per-booking invariants:
 *        a payment posted without an escrow row,
 *        b refunded bookings whose signed sum is not zero,
 *        c partners credited more than the escrow remainder holds
 *          (catches commission-rule vs booking-time fee mismatches),
 *        d negative escrow without a refund.
 *   3. Cache drift - PartnerBalance.available_balance vs the ledger-derived
 *      balance (last balance_after). --fix-cache recalculates drifted rows.
 *
 * Exit code 1 when any issue is found (CI / cron friendly).
 */
class ReconcileLedger extends Command
{
    protected $signature = 'ledger:reconcile
        {--user= : Only check this user id}
        {--booking= : Only check this booking id}
        {--limit=15 : Max issues listed per category}
        {--fix-cache : Recalculate PartnerBalance rows that drifted}';

    protected $description = 'Detect ledger discrepancies: broken balance chains, conservation violations, cached-balance drift';

    private int $chainIssues = 0;

    private int $bookingIssues = 0;

    private int $cacheIssues = 0;

    public function handle(): int
    {
        $this->scanChains();
        $this->scanBookings();
        $this->scanCache();

        $this->newLine();
        $total = $this->chainIssues + $this->bookingIssues + $this->cacheIssues;
        if ($total === 0) {
            $this->info('LEDGER OK: no discrepancies found.');
            return self::SUCCESS;
        }

        $this->error("LEDGER ISSUES FOUND: {$this->chainIssues} chain, {$this->bookingIssues} booking, {$this->cacheIssues} cache.");
        return self::FAILURE;
    }

    private function signed(LedgerEntry $entry): float
    {
        return LedgerEntry::signedAmount($entry->type, (float) $entry->amount);
    }

    private function scanChains(): void
    {
        $this->line('1) CHAIN INTEGRITY (balance_after continuity per user)');
        $limit = (int) $this->option('limit');

        $userIds = LedgerEntry::query()
            ->when($this->option('user'), fn ($q, $u) => $q->where('user_id', (int) $u))
            ->selectRaw('DISTINCT user_id')
            ->pluck('user_id');

        $checked = 0;
        foreach ($userIds as $userId) {
            $prev = 0.0;
            foreach (
                LedgerEntry::where('user_id', $userId)
                    ->orderBy('created_at')
                    ->orderBy('id')
                    ->get(['id', 'type', 'amount', 'balance_after']) as $entry
            ) {
                $expected = round($prev + $this->signed($entry), 2);
                if (abs((float) $entry->balance_after - $expected) > 0.01) {
                    $this->chainIssues++;
                    if ($this->chainIssues <= $limit) {
                        $this->error("   user #{$userId} entry #{$entry->id} [{$entry->type}]: stored {$entry->balance_after}, expected {$expected}");
                    }
                }
                $prev = (float) $entry->balance_after;
                $checked++;
            }
        }
        $this->line("   scanned " . $userIds->count() . " users / {$checked} entries");
        if ($this->chainIssues > $limit) {
            $this->line('   ...' . ($this->chainIssues - $limit) . ' more chain issues not shown');
        }
        $this->newLine();
    }

    private function scanBookings(): void
    {
        $this->line('2) BOOKING CONSERVATION');
        $limit = (int) $this->option('limit');

        $entries = LedgerEntry::query()
            ->whereNotNull('booking_id')
            ->when($this->option('booking'), fn ($q, $b) => $q->where('booking_id', $b))
            ->orderBy('id')
            ->get();

        $byBooking = $entries->groupBy('booking_id');
        $scanned = 0;

        foreach ($byBooking as $bookingId => $rows) {
            $scanned++;
            $debit = (float) $rows->where('type', 'CUSTOMER_DEBIT')->sum('amount');
            $refund = (float) $rows->where('type', 'REFUND')->sum('amount');
            $escrowRemainder = round(
                $rows->whereIn('type', ['ESCROW_CREDIT', 'PLATFORM_FEE', 'TAX_PAYABLE'])
                    ->sum(fn ($e) => $this->signed($e)),
                2
            );
            $partnerNet = round(
                (float) $rows->where('type', 'PARTNER_EARNING')->sum('amount')
                - (float) $rows->where('type', 'EARNING_REVERSAL')->sum('amount'),
                2
            );
            $totalSigned = round($rows->sum(fn ($e) => $this->signed($e)), 2);

            $flags = [];
            if ($debit > 0 && (float) $rows->where('type', 'ESCROW_CREDIT')->sum('amount') === 0.0) {
                $flags[] = 'customer debited but no ESCROW_CREDIT posted';
            }
            if ($refund > 0 && abs($totalSigned) > 0.02) {
                $flags[] = "refunded booking sums to {$totalSigned}, expected 0";
            }
            if ($partnerNet > $escrowRemainder + 0.02 && abs($escrowRemainder) > 0.001) {
                $flags[] = "partner credited {$partnerNet} but escrow remainder holds only {$escrowRemainder}";
            }
            if ($refund == 0.0 && $escrowRemainder < -0.02) {
                $flags[] = "negative escrow remainder {$escrowRemainder} without refund";
            }

            foreach ($flags as $flag) {
                $this->bookingIssues++;
                if ($this->bookingIssues <= $limit) {
                    $this->error("   booking {$bookingId}: {$flag}");
                }
            }
        }

        $this->line("   scanned {$scanned} bookings");
        if ($this->bookingIssues > $limit) {
            $this->line('   ...' . ($this->bookingIssues - $limit) . ' more booking issues not shown');
        }
        $this->newLine();
    }

    private function scanCache(): void
    {
        $this->line('3) PARTNER BALANCE CACHE vs LEDGER');
        $limit = (int) $this->option('limit');
        $fix = (bool) $this->option('fix-cache');

        $balances = PartnerBalance::query()
            ->when($this->option('user'), fn ($q, $u) => $q->where('partner_id', (int) $u))
            ->get();

        foreach ($balances as $balance) {
            $derived = (float) (LedgerEntry::where('user_id', $balance->partner_id)
                ->orderByDesc('created_at')
                ->orderByDesc('id')
                ->value('balance_after') ?? 0);
            $drift = round((float) $balance->available_balance - $derived, 2);

            if (abs($drift) > 0.02) {
                $this->cacheIssues++;
                if ($fix) {
                    PartnerBalance::recalculateForPartner($balance->partner_id);
                    $this->line("   FIXED partner #{$balance->partner_id}: was {$balance->available_balance}, ledger says {$derived}");
                } elseif ($this->cacheIssues <= $limit) {
                    $this->error("   partner #{$balance->partner_id}: cached {$balance->available_balance}, ledger-derived {$derived} (drift {$drift}) - run with --fix-cache to repair");
                }
            }
        }

        $this->line('   scanned ' . $balances->count() . ' partner balance rows');
        if ($this->cacheIssues > $limit) {
            $this->line('   ...' . ($this->cacheIssues - $limit) . ' more drift issues not shown');
        }
    }
}
