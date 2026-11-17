<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\CreatesApplication;
use Tests\TestCase;
use App\Models\Address;
use App\Models\Category;
use App\Models\LedgerEntry;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Service;
use App\Models\ServicePricing;
use App\Models\User;
use App\Services\PaymentService;

/**
 * Idempotency of the payment verification / webhook path.
 *
 * Gateway callbacks are retried and can arrive out of order. The system must:
 *  - never double-post money or ledger entries on duplicate success callbacks
 *  - never cancel a paid/completed order on a stale failure callback
 *  - keep the order's transaction_uuid in sync with the current payment session
 */
class PaymentWebhookIdempotencyTest extends TestCase
{
    use CreatesApplication, RefreshDatabase;

    private User $customer;
    private Address $address;
    private Service $service;
    private string $scheduledAt;

    protected function setUp(): void
    {
        parent::setUp();

                // The platform/system account that owns ESCROW_CREDIT, PLATFORM_FEE
        // and TAX_PAYABLE ledger rows (hardcoded as user_id 1 in
        // PaymentLedgerService). The FK requires it to exist.
        User::unguarded(fn () => User::create([
            'user_id' => 1,
            'first_name' => 'Platform',
            'last_name' => 'System',
            'email' => 'platform@homify.local',
            'phone' => '9800000001',
            'password' => bcrypt('secret'),
            'user_type' => 'admin',
            'is_active' => true,
        ]));

        // An explicit user_id=1 insert does not advance the Postgres sequence;
        // after a fresh migrate the next User::create would collide with id 1.
        \Illuminate\Support\Facades\DB::statement(
            "SELECT setval('users_user_id_seq', GREATEST((SELECT COALESCE(MAX(user_id), 1) FROM users), 1))"
        );

        $this->customer = User::create([
            'first_name' => 'Test',
            'last_name' => 'Customer',
            'email' => fake()->unique()->safeEmail(),
            'phone' => '98' . rand(10000000, 99999999),
            'password' => bcrypt('password'),
            'user_type' => 'customer',
            'is_active' => true,
        ]);

        $this->address = Address::create([
            'user_id' => $this->customer->user_id,
            'address_line_1' => 'Test Street',
            'city' => 'Kathmandu',
            'state' => 'Bagmati',
            'postal_code' => '44600',
            'country' => 'Nepal',
            'is_default' => true,
        ]);

        $category = Category::create([
            'name' => 'Cleaning',
            'is_active' => true,
        ]);

        $this->service = Service::create([
            'category_id' => $category->category_id,
            'name' => 'Home Cleaning',
            'description' => 'Standard home cleaning service',
            'is_active' => true,
            'requires_prepay' => false,
        ]);

        ServicePricing::create([
            'category_id' => $category->category_id,
            'service_id' => $this->service->service_id,
            'tier_name' => 'standard',
            'base_price' => 1500,
            'is_active' => true,
        ]);

        $this->scheduledAt = now()->addDay()->setTime(10, 0)->format('Y-m-d H:i');
    }

    private function createOnlineOrder(): Order
    {
        Sanctum::actingAs($this->customer, ['*']);
        $response = $this->postJson('/api/v1/bookings', [
            'services' => [
                ['service_id' => $this->service->service_id, 'quantity' => 1],
            ],
            'scheduled_at' => $this->scheduledAt,
            'address_id' => $this->address->address_id,
            'payment_method' => 'online',
        ]);
        $response->assertStatus(201);
        return Order::findOrFail($response->json('order_id'));
    }

    private function fakeGatewaySuccess(float $totalAmount): void
    {
        Http::fake([
            '*api/epay/transaction/status*' => Http::response([
                'status' => 'COMPLETE',
                'ref_id' => 'REF-1',
                'total_amount' => number_format($totalAmount, 2, '.', ''),
            ], 200),
        ]);
    }

    public function test_duplicate_success_callback_is_idempotent(): void
    {
        $order = $this->createOnlineOrder();
        $payment = Payment::where('booking_id', $order->order_id)->first();
        $this->assertNotNull($payment);
        $this->assertSame('initiated', $payment->status);

        $this->fakeGatewaySuccess((float) $order->total_amount);
        $service = app(PaymentService::class);

        $first = $service->verifyAndUpdatePayment('esewa', [
            'transaction_uuid' => $payment->txn_id,
            'total_amount' => $order->total_amount,
        ]);
        $this->assertTrue($first['success']);

        // Gateway retry / redirect + webhook double-fire: same txn again.
        $second = $service->verifyAndUpdatePayment('esewa', [
            'transaction_uuid' => $payment->txn_id,
            'total_amount' => $order->total_amount,
        ]);
        $this->assertTrue($second['success']);
        $this->assertTrue($second['already_paid'] ?? false);

        $this->assertSame('paid', $payment->refresh()->status);
        $this->assertSame('confirmed', $order->refresh()->status);
        $this->assertSame('paid', $order->payment_status);

        // Ledger entries posted exactly once.
        $this->assertSame(1, LedgerEntry::where('booking_id', $order->order_id)->where('type', 'CUSTOMER_DEBIT')->count());
        $this->assertSame(1, LedgerEntry::where('booking_id', $order->order_id)->where('type', 'ESCROW_CREDIT')->count());
    }

    public function test_failure_callback_after_success_is_ignored(): void
    {
        $order = $this->createOnlineOrder();
        $payment = Payment::where('booking_id', $order->order_id)->first();

        $this->fakeGatewaySuccess((float) $order->total_amount);
        $service = app(PaymentService::class);
        $service->verifyAndUpdatePayment('esewa', [
            'transaction_uuid' => $payment->txn_id,
            'total_amount' => $order->total_amount,
        ]);
        $this->assertSame('paid', $payment->refresh()->status);

        // A stale failure callback for the same txn must be a no-op: the
        // already-paid payment is acknowledged, never marked failed, and the
        // confirmed order is never cancelled.
        $lateFailure = $service->verifyAndUpdatePayment('esewa', [
            'transaction_uuid' => $payment->txn_id,
            'total_amount' => $order->total_amount,
        ]);
        $this->assertTrue($lateFailure['success']);
        $this->assertTrue($lateFailure['already_paid'] ?? false);

        $this->assertSame('paid', $payment->refresh()->status);
        $this->assertSame('confirmed', $order->refresh()->status);
        $this->assertSame('paid', $order->payment_status);
        $this->assertNotSame('cancelled', $order->serviceRequests()->first()?->status);
    }

    public function test_failure_redirect_after_paid_does_not_cancel_order(): void
    {
        $order = $this->createOnlineOrder();
        $payment = Payment::where('booking_id', $order->order_id)->first();

        $this->fakeGatewaySuccess((float) $order->total_amount);
        app(PaymentService::class)->verifyAndUpdatePayment('esewa', [
            'transaction_uuid' => $payment->txn_id,
            'total_amount' => $order->total_amount,
        ]);
        $this->assertSame('paid', $order->refresh()->payment_status);

        // The eSewa failure redirect arrives AFTER the money landed.
        $this->getJson('/api/v1/webhooks/esewa/failure?transaction_uuid=' . $order->transaction_uuid)
            ->assertStatus(302);

        $order->refresh();
        $this->assertSame('confirmed', $order->status);
        $this->assertSame('paid', $order->payment_status);
        $this->assertNotSame('cancelled', $order->serviceRequests()->first()?->status);
    }

    public function test_retry_payment_refreshes_transaction_uuid(): void
    {
        $order = $this->createOnlineOrder();
        $oldUuid = $order->transaction_uuid;
        $this->assertNotNull($oldUuid);

        Sanctum::actingAs($this->customer, ['*']);
        $response = $this->postJson("/api/v1/bookings/retry-payment/{$order->order_id}");
        $response->assertStatus(200)->assertJsonPath('success', true);

        $newUuid = $response->json('payload.transaction_uuid');
        $this->assertNotNull($newUuid);
        $this->assertNotSame($oldUuid, $newUuid);

        // The order must point at the CURRENT session, so failure/timeout
        // handling can never target the superseded one.
        $this->assertSame($newUuid, $order->refresh()->transaction_uuid);
        $this->assertSame($newUuid, Payment::where('booking_id', $order->order_id)->where('gateway', 'esewa')->first()->txn_id);
    }
}
