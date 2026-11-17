<?php

namespace App\Console\Commands;

use App\Models\Payment;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ExpireOldPayments extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'payments:expire
                            {--dry-run : Run without making changes}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Mark expired payments as failed (payments that passed expiry time)';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $isDryRun = $this->option('dry-run');

        // Find all expired payments that are still in 'initiated' status
        $expiredPayments = Payment::where('status', 'initiated')
            ->where('expires_at', '<', now())
            ->get();

        $count = $expiredPayments->count();

        if ($count === 0) {
            $this->info('No expired payments found.');
            return 0;
        }

        $this->info("Found {$count} expired payment(s).");

        if ($isDryRun) {
            $this->warn('DRY RUN MODE - No changes will be made');
            $this->table(
                ['Payment ID', 'Order ID', 'Amount', 'Expired At', 'Gateway'],
                $expiredPayments->map(function ($payment) {
                    return [
$payment->id,
                        $payment->order_id,
                        'Rs. ' . number_format($payment->amount, 2),
                        $payment->expires_at->diffForHumans(),
                        $payment->gateway
                    ];
                })->toArray()
            );
            return 0;
        }

        // Mark as expired
        $bar = $this->output->createProgressBar($count);
        $bar->start();

        foreach ($expiredPayments as $payment) {
            try {
                // Compare-and-swap: re-check the payment state under a row lock
                // so a concurrent webhook that settled this payment can never be
                // overwritten by a stale expiry (money landed + payment marked
                // failed would leave the order paid but the payment row failed).
                $expired = DB::transaction(function () use ($payment) {
                    $lockedPayment = Payment::whereKey($payment->id)->lockForUpdate()->first();

                    if (!$lockedPayment || $lockedPayment->status !== 'initiated') {
                        return false;
                    }

                    // Never expire a payment whose booking was already settled
                    // (paid, completed or cash-settled) - a late expiry would
                    // overwrite the settled state. The order is locked too so
                    // the check and the update are atomic.
                    $order = $lockedPayment->booking()->lockForUpdate()->first();

                    if (
                        $order
                        && ($order->payment_status === 'paid'
                            || $order->status === 'completed'
                            || $order->payment_received === 'cash')
                    ) {
                        Log::warning('Skipping expiry for settled order', [
                            'payment_id' => $payment->payment_id,
                            'order_id' => $payment->booking_id,
                            'payment_status' => $order->payment_status,
                            'order_status' => $order->status,
                        ]);
                        return false;
                    }

                    $lockedPayment->markAsFailed('Payment expired');

                    // Also update the order payment status
                    if ($order) {
                        $order->update([
                            'payment_status' => 'failed'
                        ]);
                    }

                    Log::info('Payment expired', [
                        'payment_id' => $payment->payment_id,
                        'order_id' => $payment->booking_id,
                        'amount' => $payment->amount,
                        'expired_at' => $payment->expires_at
                    ]);

                    return true;
                });

                if ($expired) {
                    $bar->advance();
                }
            } catch (\Exception $e) {
                Log::error('Failed to expire payment', [
                    'payment_id' => $payment->payment_id,
                    'error' => $e->getMessage()
                ]);
            }
        }

        $bar->finish();
        $this->newLine();
        $this->info("Successfully expired {$count} payment(s).");

        return 0;
    }
}
