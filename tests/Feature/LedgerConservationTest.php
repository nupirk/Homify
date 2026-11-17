<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\CreatesApplication;
use Tests\TestCase;
use App\Models\Category;
use App\Models\CommissionRule;
use App\Models\LedgerEntry;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\PartnerProfile;
use App\Models\Service;
use App\Models\ServiceRequest;
use App\Models\User;
use App\Services\LedgerService;
use App\Services\PaymentLedgerService;

/**
 * Conservation regression guards for the two reversal writers that
 * historically broke the shared sign convention:
 *   - recordRefund platform-row reversals (balance moved the wrong way
 *     for debit-typed PLATFORM_FEE / TAX_PAYABLE rows),
 *   - reversePASCashSettlement commission reversal (posted positive,
 *     reading as a second deduction).
 */
class LedgerConservationTest extends TestCase
{
    use CreatesApplication, RefreshDatabase;

    private User $platform;
    private User $customer;
    private User $partner;
    private Service $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->platform = User::unguarded(fn () => User::create([
            'user_id' => 1,
            'first_name' => 'Platform',
            'last_name' => 'System',
            'email' => 'platform@urban.local',
            'phone' => '9800000001',
            'password' => bcrypt('secret'),
            'user_type' => 'admin',
            'is_active' => true,
        ]));
        \Illuminate\Support\Facades\DB::statement(
            "SELECT setval('users_user_id_seq', GREATEST((SELECT COALESCE(MAX(user_id), 1) FROM users), 1))"
        );

        $this->customer = User::create([
            'first_name' => 'Con', 'last_name' => 'Customer',
            'email' => fake()->unique()->safeEmail(),
            'phone' => '98' . rand(10000000, 99999999),
            'password' => bcrypt('password'),
            'user_type' => 'customer', 'is_active' => true,
        ]);

        $this->partner = User::create([
            'first_name' => 'Con', 'last_name' => 'Partner',
            'email' => fake()->unique()->safeEmail(),
            'phone' => '98' . rand(10000000, 99999999),
            'password' => bcrypt('password'),
            'user_type' => 'partner', 'is_active' => true,
        ]);
        PartnerProfile::unguarded(fn () => PartnerProfile::create([
            'partner_id' => $this->partner->user_id,
            'status' => 'active',
        ]));

        $category = Category::create(['name' => 'ConsCat', 'is_active' => true]);
        $this->service = Service::create([
            'category_id' => $category->category_id,
            'name' => 'Cons Service',
            'description' => 'Conservation fixture',
            'is_active' => true,
            'requires_prepay' => false,
        ]);
        CommissionRule::create([
            'service_id' => $this->service->service_id,
            'city' => null,
            'slab_start' => 0,
            'slab_end' => null,
            'partner_pct' => 80,
            'platform_pct' => 20,
            'valid_from' => now()->toDateString(),
        ]);
    }

    private function makeOrder(string $method, string $paymentStatus): Order
    {
        $order = Order::unguarded(fn () => Order::create([
            'order_id' => (string) \Illuminate\Support\Str::uuid(),
            'user_id' => $this->customer->user_id,
            'status' => 'completed',
            'payment_status' => $paymentStatus,
            'payment_method' => $method,
            'subtotal' => 1000.00,
            'tax_amount' => 130.00,
            'platform_fee' => 200.00,
            'total_amount' => 1130.00,
            'final_payable' => 1130.00,
        ]));

        $item = OrderItem::unguarded(fn () => OrderItem::create([
            'order_id' => $order->order_id,
            'service_id' => $this->service->service_id,
            'quantity' => 1,
            'unit_price' => 1000.00,
            'total_price' => 1000.00,
        ]));

        ServiceRequest::unguarded(fn () => ServiceRequest::create([
            'order_id' => $order->order_id,
            'order_item_id' => $item->item_id,
            'partner_id' => $this->partner->user_id,
            'service_date' => now()->toDateString(),
            'start_time' => '10:00:00',
            'status' => 'completed',
            'final_amount' => 1000.00,
        ]));

        return $order->fresh();
    }

    private function assertChainIntact(int $userId): void
    {
        $prev = 0.0;
        foreach (LedgerEntry::where('user_id', $userId)->orderBy('created_at')->orderBy('id')->get() as $entry) {
            $expected = round($prev + LedgerEntry::signedAmount($entry->type, (float) $entry->amount), 2);
            $this->assertEqualsWithDelta(
                $expected,
                (float) $entry->balance_after,
                0.01,
                "Chain broken at entry #{$entry->id} [{$entry->type}] for user {$userId}"
            );
            $prev = (float) $entry->balance_after;
        }
    }

    private function bookingSignedSum(string $bookingId): float
    {
        return round(
            LedgerEntry::where('booking_id', $bookingId)
                ->get()
                ->sum(fn ($e) => LedgerEntry::signedAmount($e->type, (float) $e->amount)),
            2
        );
    }

    private function lastBalance(int $userId): float
    {
        return (float) (LedgerEntry::where('user_id', $userId)
            ->orderByDesc('id')
            ->value('balance_after') ?? 0);
    }

    public function test_refund_reversal_keeps_platform_chain_and_delta_consistent(): void
    {
        $order = $this->makeOrder('online', 'paid');
        $platformBeforePayment = $this->lastBalance($this->platform->user_id);

        app(PaymentLedgerService::class)->createPaymentSuccessLedgerEntries($order);

        app(LedgerService::class)->recordRefund($order->order_id, $this->customer->user_id, 1130.00, [
            'reason' => 'conservation test',
        ]);

        $this->assertChainIntact($this->platform->user_id);
        $this->assertChainIntact($this->customer->user_id);

        $this->assertEqualsWithDelta(
            $platformBeforePayment,
            $this->lastBalance($this->platform->user_id),
            0.01,
            'Refund must return the platform wallet to its pre-payment balance'
        );

        $this->assertEqualsWithDelta(0.0, $this->bookingSignedSum($order->order_id), 0.01);
        $this->assertEqualsWithDelta(0.0, (float) LedgerEntry::getUserBalance($this->customer->user_id), 0.01);
    }

    public function test_pas_dispute_reversal_nets_booking_to_zero(): void
    {
        $order = $this->makeOrder('pas', 'pending');

        app(LedgerService::class)->recordPASReceipt($this->partner->user_id, $order->order_id, 1130.00, [
            'subtotal' => 1000.00,
        ]);

        $this->assertEqualsWithDelta(
            -330.0,
            $this->lastBalance($this->partner->user_id),
            0.01,
            'Partner owes fee+tax after cash settlement'
        );

        app(LedgerService::class)->reversePASCashSettlement($order->order_id, $this->partner->user_id);

        $this->assertChainIntact($this->partner->user_id);
        $this->assertEqualsWithDelta(0.0, $this->lastBalance($this->partner->user_id), 0.01);
        $this->assertEqualsWithDelta(0.0, (float) LedgerEntry::getUserBalance($this->partner->user_id), 0.01);
        $this->assertEqualsWithDelta(0.0, $this->bookingSignedSum($order->order_id), 0.01);
    }
}
