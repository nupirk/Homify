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
 * Khalti server-to-server webhook handling.
 *
 * Khalti pushes the transaction with pidx and purchase_order_id. The webhook
 * may also arrive with ONLY pidx (retries), so the system must match the
 * payment through the pidx stored in metadata from an earlier lookup/redirect.
 */
class PaymentWebhookKhaltiTest extends TestCase
{
    use CreatesApplication, RefreshDatabase;

    private User $customer;
    private Address $address;
    private Service $service;
    private string $scheduledAt;

    protected function setUp(): void
    {
        parent::setUp();

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

    private function fakeKhaltiInitiate(string $paymentUrl): void
    {
        Http::fake([
            '*/epayment/initiate/*' => Http::response([
                'pidx' => 'PIDX-1',
                'payment_url' => $paymentUrl,
                'expires_at' => now()->addMinutes(30)->toIso8601String(),
            ], 200),
        ]);
    }

    private function fakeKhaltiLookup(float $amountPaisa): void
    {
        Http::fake([
            '*/epayment/lookup/*' => Http::response([
                'pidx' => 'PIDX-1',
                'total_amount' => $amountPaisa,
                'status' => 'Completed',
                'transaction_id' => 'KH-REF-1',
            ], 200),
        ]);
    }

    public function test_khalti_webhook_with_only_pidx_settles_and_is_idempotent(): void
    {
        $order = $this->createOnlineOrder();

        // Initiate a khalti payment session; txn_id becomes the gateway order id.
        $this->fakeKhaltiInitiate('https://pay.khalti.test/pidx-1');
        $result = app(PaymentService::class)->initiatePayment($order, 'khalti');
        if (!$result['success']) {
            fwrite(STDERR, 'initiate error: ' . ($result['error'] ?? 'unknown') . PHP_EOL);
        }
        $this->assertTrue($result['success']);
        $payment = Payment::where('booking_id', $order->order_id)->where('gateway', 'khalti')->first();
        $this->assertNotNull($payment);
        $this->assertSame('initiated', $payment->status);

        // First webhook: Khalti pushes pidx + purchase_order_id. The lookup API
        // is keyed by pidx, so verification MUST use the pidx even though the
        // payment row is matched by purchase_order_id (txn_id).
        $this->fakeKhaltiLookup((float) ($order->total_amount * 100));
        $response = $this->postJson('/api/v1/webhooks/khalti/payment', [
            'pidx' => 'PIDX-1',
            'purchase_order_id' => $payment->txn_id,
        ]);
        $response->assertStatus(200)->assertJsonPath('success', true);

        $payment->refresh();
        $this->assertSame('paid', $payment->status);
        $this->assertSame('PIDX-1', $payment->metadata['pidx'] ?? null);
        $this->assertSame('confirmed', $order->refresh()->status);
        $this->assertSame('paid', $order->payment_status);
        $this->assertSame(1, LedgerEntry::where('booking_id', $order->order_id)->where('type', 'CUSTOMER_DEBIT')->count());

        // Retry webhook with ONLY pidx (Khalti retries can drop purchase_order_id).
        // The payment is found through the stored metadata->pidx and is a no-op.
        $retry = $this->postJson('/api/v1/webhooks/khalti/payment', [
            'pidx' => 'PIDX-1',
        ]);
        $retry->assertStatus(200)->assertJsonPath('success', true);

        // No duplicate ledger entries, still paid exactly once.
        $this->assertSame(1, LedgerEntry::where('booking_id', $order->order_id)->where('type', 'CUSTOMER_DEBIT')->count());
        $this->assertSame(1, LedgerEntry::where('booking_id', $order->order_id)->where('type', 'ESCROW_CREDIT')->count());
        $this->assertSame('paid', $payment->refresh()->status);
    }

    public function test_khalti_webhook_without_pidx_is_rejected(): void
    {
        $order = $this->createOnlineOrder();

        $this->fakeKhaltiInitiate('https://pay.khalti.test/pidx-1');
        app(PaymentService::class)->initiatePayment($order, 'khalti');

        $response = $this->postJson('/api/v1/webhooks/khalti/payment', [
            'purchase_order_id' => 'whatever',
        ]);
        $response->assertStatus(422)->assertJsonPath('message', 'pidx missing');

        $payment = Payment::where('booking_id', $order->order_id)->where('gateway', 'khalti')->first();
        $this->assertSame('initiated', $payment->status);
    }
}