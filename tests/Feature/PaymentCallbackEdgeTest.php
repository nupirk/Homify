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
use App\Models\PartnerProfile;
use App\Models\Payment;
use App\Models\Service;
use App\Models\ServiceCompletionDocument;
use App\Models\ServicePricing;
use App\Models\ServiceRequest;
use App\Models\User;
use App\Services\PaymentService;

/**
 * Webhook envelope handling, the PaymentService-level amount guard, and the
 * legacy complete() endpoint.
 */
class PaymentCallbackEdgeTest extends TestCase
{
    use CreatesApplication, RefreshDatabase;

    private User $customer;
    private User $partner;
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
        $this->syncUserIdSequence();

        $this->customer = User::create([
            'first_name' => 'Test', 'last_name' => 'Customer',
            'email' => fake()->unique()->safeEmail(),
            'phone' => '98' . rand(10000000, 99999999),
            'password' => bcrypt('password'),
            'user_type' => 'customer', 'is_active' => true,
        ]);

        $this->partner = User::create([
            'first_name' => 'Test', 'last_name' => 'Partner',
            'email' => fake()->unique()->safeEmail(),
            'phone' => '98' . rand(10000000, 99999999),
            'password' => bcrypt('password'),
            'user_type' => 'partner', 'is_active' => true,
        ]);
        PartnerProfile::unguarded(fn () => PartnerProfile::create([
            'partner_id' => $this->partner->user_id,
            'status' => 'active',
        ]));

        $this->address = Address::create([
            'user_id' => $this->customer->user_id,
            'address_line_1' => 'Test Street', 'city' => 'Kathmandu',
            'state' => 'Bagmati', 'postal_code' => '44600',
            'country' => 'Nepal', 'is_default' => true,
        ]);

        $category = Category::create(['name' => 'Cleaning', 'is_active' => true]);

        $this->service = Service::create([
            'category_id' => $category->category_id, 'name' => 'Home Cleaning',
            'description' => 'Standard home cleaning service',
            'is_active' => true, 'requires_prepay' => false,
        ]);
        ServicePricing::create([
            'category_id' => $category->category_id,
            'service_id' => $this->service->service_id,
            'tier_name' => 'standard', 'base_price' => 1500, 'is_active' => true,
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

    private function fakeEsewaStatus(float $totalAmount): void
    {
        Http::fake([
            '*api/epay/transaction/status*' => Http::response([
                'status' => 'COMPLETE',
                'ref_id' => 'REF-1',
                'total_amount' => number_format($totalAmount, 2, '.', ''),
            ], 200),
        ]);
    }

    public function test_esewa_webhook_with_base64_data_envelope_settles(): void
    {
        $order = $this->createOnlineOrder();
        $payment = Payment::where('booking_id', $order->order_id)->first();
        $this->assertNotNull($payment);

        // eSewa sends the signed payload as base64-encoded JSON in `data`.
        $this->fakeEsewaStatus((float) $order->total_amount);
        $envelope = base64_encode(json_encode([
            'transaction_uuid' => $payment->txn_id,
            'total_amount' => $order->total_amount,
        ]));

        $this->postJson('/api/v1/webhooks/esewa/payment', ['data' => $envelope])
            ->assertStatus(200)
            ->assertJsonPath('success', true);

        $this->assertSame('paid', $payment->refresh()->status);
        $this->assertSame('confirmed', $order->refresh()->status);
        $this->assertSame(1, LedgerEntry::where('booking_id', $order->order_id)->where('type', 'CUSTOMER_DEBIT')->count());
    }

    public function test_service_level_amount_guard_refuses_settlement_and_holds_payment(): void
    {
        $order = $this->createOnlineOrder();
        $payment = Payment::where('booking_id', $order->order_id)->first();

        // Gateway says PAID but with a different amount than we charged.
        // The gateway-level check (callback total vs response total) passes;
        // the Service-level guard (verified amount vs payment->amount) must
        // refuse to settle and hold the payment for review.
        $this->fakeEsewaStatus((float) $order->total_amount - 100);

        $result = app(PaymentService::class)->verifyAndUpdatePayment('esewa', [
            'transaction_uuid' => $payment->txn_id,
            'total_amount' => (float) $order->total_amount - 100, // matches gateway response
        ]);

        $this->assertFalse($result['success']);
        $payment->refresh();
        $this->assertSame('initiated', $payment->status);
        $this->assertTrue($payment->metadata['held_for_review'] ?? false);
        $this->assertTrue($payment->metadata['amount_mismatch'] ?? false);
        $this->assertSame(0, LedgerEntry::where('booking_id', $order->order_id)->count());
        $this->assertNotSame('confirmed', $order->refresh()->status);
    }

    public function test_legacy_complete_settles_only_calling_partners_lines(): void
    {
        // Two services, both assigned to the SAME partner for simplicity -
        // legacy complete() must complete the caller's lines and settle once.
        $order = $this->createOnlineOrder();
        $this->payOrderDirect($order);

        foreach ($order->serviceRequests as $sr) {
            $sr->update(['partner_id' => $this->partner->user_id, 'status' => 'in_progress', 'partner_assigned_at' => now()]);
            $filename = "legacy-{$sr->request_id}.jpg";
            ServiceCompletionDocument::unguarded(fn () => ServiceCompletionDocument::create([
                'service_request_id' => $sr->request_id,
                'partner_id' => $this->partner->user_id,
                'uploaded_by' => $this->partner->user_id,
                'document_type' => 'after_photo',
                'original_filename' => $filename,
                'stored_filename' => $filename,
                'file_path' => "completions/test/{$filename}",
                'mime_type' => 'image/jpeg',
                'file_size' => 1024,
                'file_hash' => md5($filename),
                'access_token' => \Illuminate\Support\Str::uuid()->toString(),
            ]));
        }

        Sanctum::actingAs($this->partner, ['*']);
        $this->patchJson("/api/v1/bookings/{$order->order_id}/complete", ['payment_received' => 'online'])
            ->assertStatus(200)
            ->assertJsonPath('success', true);

        $order->refresh();
        $this->assertSame('completed', $order->status);
        $this->assertSame('online', $order->payment_received);

        $earnings = LedgerEntry::where('booking_id', $order->order_id)
            ->where('user_id', $this->partner->user_id)
            ->where('type', 'PARTNER_EARNING')->get();
        $this->assertCount(1, $earnings);
        $this->assertEqualsWithDelta(1200.00, (float) $earnings->first()->amount, 0.01);

        // A second complete() call is refused - no double settlement.
        $this->patchJson("/api/v1/bookings/{$order->order_id}/complete", ['payment_received' => 'online'])
            ->assertStatus(400);
        $this->assertSame(1, LedgerEntry::where('booking_id', $order->order_id)
            ->where('type', 'PARTNER_EARNING')->count());
    }

    private function payOrderDirect(Order $order): void
    {
        $payment = Payment::where('booking_id', $order->order_id)->first();
        $this->fakeEsewaStatus((float) $order->total_amount);
        $result = app(PaymentService::class)->verifyAndUpdatePayment('esewa', [
            'transaction_uuid' => $payment->txn_id,
            'total_amount' => $order->total_amount,
        ]);
        $this->assertTrue($result['success']);
    }
}