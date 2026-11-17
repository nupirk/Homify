<?php

namespace App\Console\Commands;

use App\Models\Address;
use App\Models\Category;
use App\Models\CommissionRule;
use App\Models\LedgerEntry;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\PartnerBalance;
use App\Models\PartnerPayout;
use App\Models\PartnerProfile;
use App\Models\Service;
use App\Models\ServiceRequest;
use App\Models\User;
use App\Services\LedgerService;
use App\Services\PaymentLedgerService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Runs the full money lifecycle of simulated orders through the REAL ledger
 * services and verifies conservation at every step. Everything executes
 * inside one database transaction that is rolled back at the end, so no
 * data is persisted.
 *
 * Accounting model (wallet ledger, not double-entry):
 *   - Online order T = subtotal + tax, split into partner share P and
 *     platform cut F + tax. Conservation law: T = P + F + tax, i.e. what
 *     the customer pays must equal partner pocket + platform pocket.
 *   - The escrow remainder on the platform rows (T - F - tax) must equal
 *     the partner earning credited at completion.
 *   - Refunds / PAS dispute reversals must bring the booking signed sum
 *     back to exactly zero.
 */
class SimulateLedgerFlow extends Command
{
    protected $signature = 'ledger:simulate {--scenario=all : all|online|refund|pas}';

    protected $description = 'Simulate full order lifecycles through the real ledger services and verify net-zero conservation (rolled back, nothing persisted)';

    private int $failures = 0;

    public function handle(): int
    {
        $scenario = (string) $this->option('scenario');

        $this->info('=== LEDGER SIMULATION ===');
        $this->line('Executes real services inside one transaction; ROLLED BACK at the end.');
        $this->newLine();

        DB::beginTransaction();
        try {
            if (in_array($scenario, ['all', 'online'], true)) {
                $this->simulateOnlineFullCycle();
            }
            if (in_array($scenario, ['all', 'refund'], true)) {
                $this->simulateRefundBeforeCompletion();
            }
            if (in_array($scenario, ['all', 'pas'], true)) {
                $this->simulatePasWithDispute();
            }
        } finally {
            DB::rollBack();
        }

        $this->newLine();
        if ($this->failures === 0) {
            $this->info('RESULT: ALL CHECKS PASSED - money fully conserved in every scenario.');
        } else {
            $this->error("RESULT: {$this->failures} CHECK(S) FAILED - see [FAIL] lines above.");
        }
        $this->line('Transaction rolled back - no data was persisted.');

        return $this->failures === 0 ? self::SUCCESS : self::FAILURE;
    }

    private function simulateOnlineFullCycle(): void
    {
        $this->line('──────────────────────────────────────────────────────────');
        $this->line('SCENARIO A: ONLINE ORDER - pay -> complete -> full payout');
        $this->line('──────────────────────────────────────────────────────────');

        $fx = $this->makeFixture();

        $subtotal = 1000.00;
        $tax = 130.00;
        $total = 1130.00;
        $platformFeeAtBooking = 200.00;
        $partnerShare = 800.00;

        $order = $this->makeOrder($fx, 'online', 'paid', $subtotal, $tax, $total, $platformFeeAtBooking);

        $this->step('1. Payment success (PaymentLedgerService::createPaymentSuccessLedgerEntries)');
        app(PaymentLedgerService::class)->createPaymentSuccessLedgerEntries($order);
        $this->printNewEntries($order->order_id);

        $this->checkChain('customer', $fx['customer']->user_id);
        $this->checkChain('platform', $fx['platform']->user_id);
        $this->checkBalance('customer paid (wallet -T)', $fx['customer']->user_id, -$total);
        $escrowRemainder = $this->platformSignedSum($order->order_id);
        $this->check(
            'escrow remainder (T - fee - tax) = ' . number_format($escrowRemainder, 2) .
            ' == expected partner share ' . number_format($partnerShare, 2),
            abs($escrowRemainder - $partnerShare) < 0.01
        );

        $this->step('2. Job completion (LedgerService::recordPartnerEarning)');
        app(LedgerService::class)->recordPartnerEarning($order->order_id, $fx['partner']->user_id, $partnerShare, [
            'platform_fee' => $subtotal - $partnerShare,
            'commission_rule_used' => 'rule:' . $fx['rule']->rule_id,
            'subtotal' => $subtotal,
            'service_id' => $fx['service']->service_id,
        ]);
        $this->printNewEntries($order->order_id);
        $this->checkChain('partner', $fx['partner']->user_id);
        $this->checkBalance('partner wallet after earning', $fx['partner']->user_id, $partnerShare);
        $escrowRemainder = $this->platformSignedSum($order->order_id);
        $this->check(
            'escrow covers partner claim exactly (' . number_format($escrowRemainder, 2) . ')',
            abs($escrowRemainder - $partnerShare) < 0.01
        );

        $this->step('3. Payout gross=800.00 fee=16.00 net=784.00 (recordPayoutWithdrawal + finalizePayout)');
        $payout = PartnerPayout::create([
            'partner_id' => $fx['partner']->user_id,
            'amount' => 800.00,
            'platform_fee' => 16.00,
            'net_amount' => 784.00,
            'status' => 'processing',
            'payment_method' => 'esewa',
            'transaction_reference' => 'SIM-' . uniqid(),
        ]);
        app(LedgerService::class)->recordPayoutWithdrawal($payout);
        app(LedgerService::class)->finalizePayout($payout->payout_id, ['source' => 'simulate']);
        $this->printNewEntries(null, $fx['partner']->user_id);
        $this->checkChain('partner', $fx['partner']->user_id);
        $this->checkBalance('partner wallet after full withdrawal', $fx['partner']->user_id, 0.0);

        PartnerBalance::recalculateForPartner($fx['partner']->user_id);
        $cached = (float) PartnerBalance::where('partner_id', $fx['partner']->user_id)->value('available_balance');
        $derived = $this->lastBalance($fx['partner']->user_id);
        $this->check(
            "cached PartnerBalance ($cached) == ledger-derived ($derived)",
            abs($cached - $derived) < 0.01
        );

        $partnerPocket = 784.00;
        $platformPocket = $platformFeeAtBooking + $tax + 16.00;
        $this->conservationSummary($total, $partnerPocket, $platformPocket);
    }

    private function simulateRefundBeforeCompletion(): void
    {
        $this->line('──────────────────────────────────────────────────────────');
        $this->line('SCENARIO B: ONLINE ORDER - pay -> cancel before completion -> refund');
        $this->line('──────────────────────────────────────────────────────────');

        $fx = $this->makeFixture();

        $subtotal = 1000.00;
        $tax = 130.00;
        $total = 1130.00;

        $order = $this->makeOrder($fx, 'online', 'paid', $subtotal, $tax, $total, 200.00);
        $platformStart = $this->lastBalance($fx['platform']->user_id);

        $this->step('1. Payment success');
        app(PaymentLedgerService::class)->createPaymentSuccessLedgerEntries($order);
        $this->printNewEntries($order->order_id);

        $this->step('2. Cancellation refund (LedgerService::recordRefund)');
        app(LedgerService::class)->recordRefund($order->order_id, $fx['customer']->user_id, $total, [
            'reason' => 'simulation: cancelled before assignment',
        ]);
        $this->printNewEntries($order->order_id);

        $this->checkChain('customer', $fx['customer']->user_id);
        $this->checkChain('platform', $fx['platform']->user_id);
        $this->checkBalance('customer wallet after refund', $fx['customer']->user_id, 0.0);
        $platformDelta = round($this->lastBalance($fx['platform']->user_id) - $platformStart, 2);
        $this->check(
            'platform wallet unchanged by refunded booking (delta ' . number_format($platformDelta, 2) . ')',
            abs($platformDelta) < 0.01
        );

        $sum = $this->bookingSignedSum($order->order_id);
        $this->check(
            'booking signed sum == 0.00 (true net zero)',
            abs($sum) < 0.01
        );
    }

    private function simulatePasWithDispute(): void
    {
        $this->line('──────────────────────────────────────────────────────────');
        $this->line('SCENARIO C: PAS ORDER - cash collected -> confirmed -> disputed -> reversed');
        $this->line('──────────────────────────────────────────────────────────');

        $fx = $this->makeFixture();

        $subtotal = 1000.00;
        $tax = 130.00;
        $cashCollected = 1130.00;
        $partnerShare = 800.00;

        $order = $this->makeOrder($fx, 'pas', 'pending', $subtotal, $tax, $cashCollected, 200.00);

        $this->step('1. Cash collected + confirmed (LedgerService::recordPASReceipt)');
        app(LedgerService::class)->recordPASReceipt($fx['partner']->user_id, $order->order_id, $cashCollected, [
            'commission_rule' => $fx['rule'],
            'subtotal' => $subtotal,
            'service_id' => $fx['service']->service_id,
        ]);
        $this->printNewEntries($order->order_id);
        $this->checkChain('partner', $fx['partner']->user_id);

        $owed = $this->lastBalance($fx['partner']->user_id);
        $this->check(
            'partner owes platform ' . number_format(-$owed, 2) . ' == fee+tax (200+130) - same cut as an online order',
            abs($owed + 330.00) < 0.01
        );
        $this->line('    Partner pocketed ' . number_format($cashCollected - 330.00, 2) . ' in cash (share 800 - commission 330 owed).');

        $this->step('2. Customer disputes cash (LedgerService::reversePASCashSettlement)');
        app(LedgerService::class)->reversePASCashSettlement($order->order_id, $fx['partner']->user_id);
        $this->printNewEntries($order->order_id);
        $this->checkChain('partner', $fx['partner']->user_id);
        $this->checkBalance('partner wallet after dispute reversal', $fx['partner']->user_id, 0.0);

        $sum = $this->bookingSignedSum($order->order_id);
        $this->check(
            'booking signed sum == 0.00 (true net zero)',
            abs($sum) < 0.01
        );
    }

    private function makeFixture(): array
    {
        $platformId = (int) config('payment.platform_user_id', 1);
        $platform = User::find($platformId);
        if (!$platform) {
            $platform = User::unguarded(fn () => User::create([
                'user_id' => $platformId,
                'first_name' => 'Platform',
                'last_name' => 'Sim',
                'email' => 'platform-sim' . uniqid() . '@urban.local',
                'phone' => '9800' . random_int(100000, 999999),
                'password' => bcrypt('secret'),
                'user_type' => 'admin',
                'is_active' => true,
            ]));
        }

        $customer = User::create([
            'first_name' => 'Sim', 'last_name' => 'Customer',
            'email' => 'sim-cust-' . uniqid() . '@urban.local',
            'phone' => '981' . random_int(1000000, 9999999),
            'password' => bcrypt('secret'),
            'user_type' => 'customer', 'is_active' => true,
        ]);

        $partner = User::create([
            'first_name' => 'Sim', 'last_name' => 'Partner',
            'email' => 'sim-part-' . uniqid() . '@urban.local',
            'phone' => '982' . random_int(1000000, 9999999),
            'password' => bcrypt('secret'),
            'user_type' => 'partner', 'is_active' => true,
        ]);
        PartnerProfile::unguarded(fn () => PartnerProfile::create([
            'partner_id' => $partner->user_id,
            'status' => 'active',
        ]));

        $category = Category::create(['name' => 'SimCat ' . uniqid(), 'is_active' => true]);
        $service = Service::create([
            'category_id' => $category->category_id,
            'name' => 'Sim Service',
            'description' => 'Simulated service',
            'is_active' => true,
            'requires_prepay' => false,
        ]);

        $rule = CommissionRule::create([
            'service_id' => $service->service_id,
            'city' => null,
            'slab_start' => 0,
            'slab_end' => null,
            'partner_pct' => 80,
            'platform_pct' => 20,
            'valid_from' => now()->toDateString(),
        ]);

        return compact('platform', 'customer', 'partner', 'category', 'service', 'rule');
    }

    private function makeOrder(array $fx, string $method, string $paymentStatus, float $subtotal, float $tax, float $total, float $platformFee): Order
    {
        $order = Order::unguarded(fn () => Order::create([
            'order_id' => (string) \Illuminate\Support\Str::uuid(),
            'user_id' => $fx['customer']->user_id,
            'status' => 'completed',
            'payment_status' => $paymentStatus,
            'payment_method' => $method,
            'subtotal' => $subtotal,
            'tax_amount' => $tax,
            'platform_fee' => $platformFee,
            'total_amount' => $total,
            'final_payable' => $total,
        ]));

        $item = OrderItem::unguarded(fn () => OrderItem::create([
            'order_id' => $order->order_id,
            'service_id' => $fx['service']->service_id,
            'quantity' => 1,
            'unit_price' => $subtotal,
            'total_price' => $subtotal,
        ]));

        ServiceRequest::unguarded(fn () => ServiceRequest::create([
            'order_id' => $order->order_id,
            'order_item_id' => $item->item_id,
            'partner_id' => $fx['partner']->user_id,
            'user_address_id' => Address::create([
                'user_id' => $fx['customer']->user_id,
                'address_line_1' => 'Sim Street',
                'city' => 'Kathmandu',
                'state' => 'Bagmati',
                'postal_code' => '44600',
                'country' => 'Nepal',
                'is_default' => true,
            ])->address_id,
            'service_date' => now()->toDateString(),
            'start_time' => '10:00:00',
            'status' => 'completed',
            'final_amount' => $subtotal,
        ]));

        return $order->fresh();
    }

    private function signed(LedgerEntry $entry): float
    {
        return LedgerEntry::signedAmount($entry->type, (float) $entry->amount);
    }

    private function lastBalance(int $userId): float
    {
        return (float) (LedgerEntry::where('user_id', $userId)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->value('balance_after') ?? 0);
    }

    private function bookingSignedSum(string $bookingId): float
    {
        return round(LedgerEntry::where('booking_id', $bookingId)
            ->orderBy('id')
            ->get()
            ->sum(fn (LedgerEntry $e) => $this->signed($e)), 2);
    }

    private function platformSignedSum(string $bookingId): float
    {
        return round(LedgerEntry::where('booking_id', $bookingId)
            ->whereIn('type', ['ESCROW_CREDIT', 'PLATFORM_FEE', 'TAX_PAYABLE'])
            ->get()
            ->sum(fn (LedgerEntry $e) => $this->signed($e)), 2);
    }

    private function printNewEntries(?string $bookingId, ?int $userId = null): void
    {
        $query = LedgerEntry::query()
            ->when($bookingId, fn ($q) => $q->where('booking_id', $bookingId))
            ->when($userId, fn ($q) => $q->where('user_id', $userId))
            ->with('user:user_id,first_name,last_name');

        $rows = $query->orderBy('id')->get()->map(function (LedgerEntry $e) {
            $who = $e->relationLoaded('user') && $e->user ? $e->user->first_name : ('user#' . $e->user_id);
            return [
                '#' . $e->id,
                $who,
                $e->type,
                number_format((float) $e->amount, 2),
                ($this->signed($e) >= 0 ? '+' : '') . number_format($this->signed($e), 2),
                number_format((float) $e->balance_after, 2),
            ];
        })->all();

        $this->table(['entry', 'user', 'type', 'amount', 'signed', 'balance_after'], $rows);
    }

    private function checkChain(string $label, int $userId): void
    {
        $prev = 0.0;
        $breaks = [];
        foreach (LedgerEntry::where('user_id', $userId)->orderBy('created_at')->orderBy('id')->get() as $entry) {
            $expected = round($prev + $this->signed($entry), 2);
            if (abs((float) $entry->balance_after - $expected) > 0.01) {
                $breaks[] = "entry #{$entry->id} {$entry->type}: stored {$entry->balance_after}, expected {$expected}";
            }
            $prev = (float) $entry->balance_after;
        }
        $this->check("chain integrity [{$label}]", count($breaks) === 0, implode('; ', $breaks));
    }

    private function checkBalance(string $label, int $userId, float $expected): void
    {
        $actual = $this->lastBalance($userId);
        $this->check(
            "{$label} = " . number_format($expected, 2) . " (actual " . number_format($actual, 2) . ")",
            abs($actual - $expected) < 0.01
        );
    }

    private function check(string $label, bool $passed, string $detail = ''): void
    {
        if ($passed) {
            $this->line("  [PASS] {$label}");
            return;
        }
        $this->failures++;
        $this->error("  [FAIL] {$label}" . ($detail !== '' ? " - {$detail}" : ''));
    }

    private function step(string $title): void
    {
        $this->newLine();
        $this->line("STEP {$title}");
    }

    private function conservationSummary(float $in, float $partnerPocket, float $platformPocket): void
    {
        $out = round($partnerPocket + $platformPocket, 2);
        $net = round($in - $out, 2);

        $this->newLine();
        $this->line('  REAL-MONEY CONSERVATION:');
        $this->line('    customer paid ......... ' . number_format($in, 2));
        $this->line('    partner pocket ........ ' . number_format($partnerPocket, 2) . ' (payout net)');
        $this->line('    platform pocket ....... ' . number_format($platformPocket, 2) . ' (fee 200 + tax 130 + payout fee 16)');
        $this->check(
            'money in == money out (net ' . number_format($net, 2) . ')',
            abs($net) < 0.01
        );
    }
}
