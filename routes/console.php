<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use App\Models\LedgerEntry;
use App\Models\User;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('test:earnings', function () {
    try {
        $this->info('Testing Earnings API Logic');

        // Get a partner with earnings
        $earning = LedgerEntry::where('type', 'PARTNER_EARNING')->first();
        if (!$earning) {
            $this->error('No partner earnings found');
            return;
        }

        $partnerId = $earning->user_id;
        $this->info("Testing with partner ID: {$partnerId}");

        // Test the controller logic
        $earningsQuery = LedgerEntry::where('user_id', $partnerId)
            ->where('type', 'PARTNER_EARNING')
            ->with(['order'])
            ->orderBy('created_at', 'desc');

        $earnings = $earningsQuery->limit(5)->get();
        $this->info("Found {$earnings->count()} earnings");

        foreach ($earnings as $earning) {
            $this->info("  Processing earning {$earning->id}");

            $order = $earning->order;
            if (!$order) {
                $this->warn("  No order found for earning {$earning->id}");
                continue;
            }

            // Get related ledger entries
            $relatedEntries = LedgerEntry::where('booking_id', $earning->booking_id)->get();

            $customerDebit = $relatedEntries->firstWhere('type', 'CUSTOMER_DEBIT');
            $taxPayable = $relatedEntries->firstWhere('type', 'TAX_PAYABLE');
            $partnerEarning = $relatedEntries->firstWhere('type', 'PARTNER_EARNING');

            if (!$customerDebit) {
                $this->warn("  No CUSTOMER_DEBIT found");
                continue;
            }

            $basePrice = $customerDebit->amount;
            if ($taxPayable) {
                $basePrice -= $taxPayable->amount;
            }

            $partnerEarningAmount = $partnerEarning ? $partnerEarning->amount : 0;
            $platformFee = $basePrice - $partnerEarningAmount;

            $this->info("  Base Price: Rs {$basePrice}");
            $this->info("  Platform Fee: Rs {$platformFee}");
            $this->info("  Partner Earning: Rs {$partnerEarningAmount}");
        }

        $this->info('Test completed successfully!');
    } catch (Exception $e) {
        $this->error('Error: ' . $e->getMessage());
        $this->error('Trace: ' . $e->getTraceAsString());
    }
})->purpose('Test earnings API logic');

// Partner Assignment Management Commands
use Illuminate\Support\Facades\Schedule;

// Register the expired assignments handler command
Artisan::command('bookings:handle-expired-assignments', function () {
    $this->call(\App\Console\Commands\HandleExpiredAssignments::class);
})->purpose('Handle partner assignments that have expired without response');

// Schedule the command to run every 5 minutes
Schedule::command('bookings:handle-expired-assignments')->everyFiveMinutes()->withoutOverlapping();

// Auto-assign pending service requests to the best available partner (every
// minute). withoutOverlapping() stops a slow run from stacking on top of the
// next tick - once per minute is tight enough that a backlog would otherwise
// double-assign the same request.
Schedule::command('bookings:auto-assign')->everyMinute()->withoutOverlapping();

Artisan::command('bookings:cancel-expired', function () {
    \App\Jobs\CancelExpiredBookings::dispatchSync();
})->purpose('Cancel expired pending payments');

// Schedule expired payments cleanup
Schedule::command('bookings:cancel-expired')->everyFiveMinutes()->withoutOverlapping();

// Schedule payment expiry cleanup
Schedule::command('payments:expire')->everyFiveMinutes()->withoutOverlapping();

// Nudge customers about unconfirmed cash payments and escalate the stale ones.
// Hourly is plenty: the thresholds are measured in days.
Schedule::command('bookings:remind-cash-confirmations')->hourly()->withoutOverlapping();

// Nightly database backup with retention pruning (needs pg_dump on PATH or
// PG_DUMP_BINARY set). Kept off-peak to avoid competing with traffic.
Schedule::command('db:backup')->dailyAt('02:30')->withoutOverlapping();
