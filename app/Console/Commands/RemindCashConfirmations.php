<?php

namespace App\Console\Commands;

use App\Models\Notification;
use App\Models\Order;
use App\Services\AuditService;
use App\Services\NotificationService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Pay-after-service cash jobs are settled by the CUSTOMER confirming that they
 * handed cash over. Nothing forced that confirmation, so an unconfirmed job sat
 * in "Awaiting Payment" forever with the partner's commission unbooked.
 *
 * This nudges the customer once the job is a day old, and hands the booking to
 * support when it is still unconfirmed after three days. It never confirms
 * money on the customer's behalf - that is a financial decision only they (or
 * support) can make.
 */
class RemindCashConfirmations extends Command
{
    protected $signature = 'bookings:remind-cash-confirmations {--dry-run : Report what would be sent without writing}';

    protected $description = 'Remind customers to confirm cash payments; escalate stale ones to support';

    private const REMINDER_TITLE = 'Please confirm your cash payment';

    public function handle(): int
    {
        $isDryRun = (bool) $this->option('dry-run');

        $reminderAfter = now()->subHours((int) config('payment.cash_reminder_hours', 24));
        $escalateAfter = now()->subHours((int) config('payment.cash_escalation_hours', 72));

        $orders = Order::where('payment_method', 'pas')
            ->where('payment_received', 'cash')
            ->where('cash_confirmed', false)
            ->where('cash_disputed', false)
            ->where('status', 'completed')
            ->where('updated_at', '<', $reminderAfter)
            ->get();

        if ($orders->isEmpty()) {
            $this->info('No unconfirmed cash payments to chase.');

            return 0;
        }

        $reminded = 0;
        $escalated = 0;

        foreach ($orders as $order) {
            $shortId = strtoupper(substr($order->order_id, -8));

            // Once support has been asked to settle a stale booking, stop
            // nudging (and stop re-escalating): the row is theirs now.
            if (str_contains((string) $order->admin_notes, 'CASH CONFIRMATION OVERDUE')) {
                continue;
            }

            $hoursUnconfirmed = $order->updated_at
                ? (int) $order->updated_at->diffInHours(now())
                : 0;

            // Past the escalation window the booking is waiting on support, not
            // on another nudge - stop reminding and flag it instead.
            if ($order->updated_at < $escalateAfter) {
                if ($isDryRun) {
                    $this->line("would escalate #{$shortId}");

                    continue;
                }
                if ($this->escalate($order, $shortId, $hoursUnconfirmed)) {
                    $escalated++;
                }

                continue;
            }

            if ($isDryRun) {
                $this->line("would remind #{$shortId} (NPR " . number_format((float) $order->total_amount, 2) . ')');

                continue;
            }

            if ($this->alreadyReminded($order, $shortId)) {
                continue;
            }

            try {
                NotificationService::sendWithPush(
                    $order->user_id,
                    'cash_confirmation_reminder',
                    self::REMINDER_TITLE,
                    "The service provider for booking #{$shortId} recorded a cash payment of NPR "
                        . number_format((float) $order->total_amount, 2)
                        . '. Confirm it in the app to close the booking.',
                    ['order_id' => $order->order_id, 'action' => 'confirm_cash']
                );
                $reminded++;
            } catch (\Exception $e) {
                Log::warning('Cash confirmation reminder failed', [
                    'order_id' => $order->order_id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $this->info("Cash confirmations: {$reminded} reminder(s), {$escalated} escalated.");

        return 0;
    }

    /**
     * NotificationService only suppresses duplicates for 10 minutes, so the
     * command keeps its own longer window: one reminder per booking per 3 days.
     */
    private function alreadyReminded(Order $order, string $shortId): bool
    {
        return Notification::where('user_id', $order->user_id)
            ->where('title', self::REMINDER_TITLE)
            ->where('message', 'like', "%#{$shortId}%")
            ->where('created_at', '>=', now()->subDays(3))
            ->exists();
    }

    /**
     * Flag the booking for support exactly once. The admin_notes marker and the
     * audit row are what make it visible to whoever reviews the order.
     */
    private function escalate(Order $order, string $shortId, int $hoursUnconfirmed = 0): bool
    {
        $marker = "CASH CONFIRMATION OVERDUE: booking #{$shortId}";

        if (str_contains((string) $order->admin_notes, $marker)) {
            return false;
        }

        if ($hoursUnconfirmed <= 0) {
            $hoursUnconfirmed = $order->updated_at
                ? (int) $order->updated_at->diffInHours(now())
                : 0;
        }

        try {
            $order->update([
                'admin_notes' => ($order->admin_notes ? $order->admin_notes . "\n\n" : '')
                    . $marker . ' - the customer has not confirmed the cash payment after '
                    . (int) config('payment.cash_escalation_hours', 72)
                    . ' hours. Verify with the partner and settle manually if needed.',
            ]);

            AuditService::record(null, 'cash_confirmation_overdue', 'order', $order->order_id, [
                'cash_confirmed' => false,
            ], [
                'escalated' => true,
                'hours_unconfirmed' => $hoursUnconfirmed,
            ]);

            Log::warning('Cash confirmation escalated to support', ['order_id' => $order->order_id]);

            return true;
        } catch (\Exception $e) {
            Log::error('Cash confirmation escalation failed', [
                'order_id' => $order->order_id,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }
}
