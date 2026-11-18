<?php

namespace App\Console\Commands;

use App\Models\OtpVerification;
use Illuminate\Console\Command;

/**
 * OTP rows were never removed, so the table grew forever and kept stale
 * (expired) code material around. Anything older than the grace window is
 * useless to every endpoint; delete it.
 */
class PruneOtps extends Command
{
    protected $signature = 'otp:prune {--days=1 : Keep records that expired within this many days}';

    protected $description = 'Delete OTP records that expired more than a day ago';

    public function handle(): int
    {
        $days = max(0, (int) $this->option('days'));
        $cutoff = now()->subDays($days);

        $deleted = OtpVerification::where('expires_at', '<', $cutoff)->delete();

        $this->info("Pruned {$deleted} expired OTP record(s) older than {$days} day(s).");

        return self::SUCCESS;
    }
}
