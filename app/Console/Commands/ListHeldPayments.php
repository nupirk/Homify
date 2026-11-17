<?php

namespace App\Console\Commands;

use App\Models\Payment;
use Illuminate\Console\Command;

class ListHeldPayments extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'payments:held {--include-expired : Also list held payments that have since expired}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'List payments held for manual review after an amount mismatch';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $query = Payment::where('metadata->held_for_review', true)
            ->orWhere('metadata->amount_mismatch', true);

        if (!$this->option('include-expired')) {
            $query->where(function ($q) {
                $q->where('status', 'initiated')
                    ->orWhere('status', 'paid');
            });
        }

        $held = $query->orderBy('created_at', 'desc')->get();

        if ($held->isEmpty()) {
            $this->info('No payments held for review.');
            return 0;
        }

        $this->info("Found {$held->count()} payment(s) held for review.");

        $this->table(
            ['Payment ID', 'Order ID', 'Gateway', 'Amount', 'Status', 'Held At', 'Created At'],
            $held->map(function (Payment $payment) {
                return [
                    $payment->id,
                    $payment->booking_id,
                    $payment->gateway,
                    'Rs. ' . number_format((float) $payment->amount, 2),
                    $payment->status,
                    $payment->metadata['held_for_review'] ? $payment->metadata['failed_at'] ?? 'yes' : 'yes',
                    $payment->created_at,
                ];
            })->toArray()
        );

        return 0;
    }
}