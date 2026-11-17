<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\CreatesApplication;
use Tests\TestCase;
use App\Models\Address;
use App\Models\Category;
use App\Models\Order;
use App\Models\PartnerProfile;
use App\Models\Service;
use App\Models\ServiceCompletionDocument;
use App\Models\ServicePricing;
use App\Models\User;
use App\Services\PaymentService;

/**
 * The customer's "Awaiting Payment" settlement inbox (GET /bookings/unpaid):
 *
 *   1. online orders still owed money (abandoned checkout / dead session),
 *   2. PAS orders whose work is fully done and nobody paid (Pay Now settles
 *      them via retry-payment -> webhook),
 *   3. PAS orders where cash was collected at the door and the customer has
 *      not confirmed yet (Confirm Cash Payment).
 *
 * Also pins: Pay Now issues a fresh eSewa session for a PAS order, and a
 * prepaid completion ignores a spoofed payment_received=cash declaration.
 */
class SettlementInboxTest extends TestCase
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

        $this->customer = User::create([
            'first_name' => 'Inbox',
            'last_name' => 'Customer',
            'email' => fake()->unique()->safeEmail(),
            'phone' => '98' . rand(10000000, 99999999),
            'password' => bcrypt('password'),
            'user_type' => 'customer',
            'is_active' => true,
        ]);

        $this->partner = User::create([
            'first_name' => 'Inbox',
            'last_name' => 'Partner',
            'email' => fake()->unique()->safeEmail(),
            'phone' => '98' . rand(10000000, 99999999),
            'password' => bcrypt('password'),
            'user_type' => 'partner',
            'is_active' => true,
        ]);

        PartnerProfile::unguarded(function () {
            PartnerProfile::create([
                'partner_id' => $this->partner->user_id,
                'status' => 'active',
            ]);
        });

        $this->address = Address::create([
            'user_id' => $this->customer->user_id,
            'address_line_1' => 'Test Street',
            'city' => 'Kathmandu',
            'state' => 'Bagmati',
            'postal_code' => '44600',
            'country' => 'Nepal',
            'is_default' => true,
        ]);

        $category = Category::create(['name' => 'Cleaning', 'is_active' => true]);

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

        $this->partner->partnerProfile->services()->attach($this->service->service_id);

        $this->scheduledAt = now()->addDay()->setTime(10, 0)->format('Y-m-d H:i');
    }

    private function createOrder(string $paymentMethod = 'pas'): Order
    {
        Sanctum::actingAs($this->customer, ['*']);
        $response = $this->postJson('/api/v1/bookings', [
            'services' => [
                ['service_id' => $this->service->service_id, 'quantity' => 1],
            ],
            'scheduled_at' => $this->scheduledAt,
            'address_id' => $this->address->address_id,
            'preferred_partner_id' => $this->partner->user_id,
            'payment_method' => $paymentMethod,
        ]);
        $response->assertStatus(201);

        return Order::findOrFail($response->json('order_id'));
    }

    private function partnerAcceptsAndStarts(Order $order): void
    {
        Sanctum::actingAs($this->partner, ['*']);
        $this->patchJson("/api/v1/bookings/{$order->order_id}/accept")->assertStatus(200);
        $this->patchJson("/api/v1/bookings/{$order->order_id}/start-work")->assertStatus(200);
    }

    private function markAllRequestsCompleted(Order $order): void
    {
        $order->serviceRequests()->update(['status' => 'completed']);
    }

    private function addCompletionDocument(Order $order): void
    {
        $request = $order->serviceRequests()->first();
        ServiceCompletionDocument::unguarded(function () use ($request) {
            ServiceCompletionDocument::create([
                'service_request_id' => $request->request_id,
                'partner_id' => $this->partner->user_id,
                'uploaded_by' => $this->partner->user_id,
                'document_type' => 'after_photo',
                'original_filename' => 'after.jpg',
                'stored_filename' => 'after.jpg',
                'file_path' => 'completions/test/after.jpg',
                'mime_type' => 'image/jpeg',
                'file_size' => 1024,
                'file_hash' => 'abc123',
                'access_token' => \Illuminate\Support\Str::uuid()->toString(),
            ]);
        });
    }

    private function inboxIds(): array
    {
        Sanctum::actingAs($this->customer, ['*']);
        $json = $this->getJson('/api/v1/bookings/unpaid')->assertStatus(200)->json();

        return collect($json['data'] ?? [])->pluck('id')->all();
    }

    public function test_online_unpaid_order_appears_in_inbox(): void
    {
        $order = $this->createOrder('online');

        // Online + preferred partner stays in 'created' until money lands.
        $this->assertContains($order->order_id, $this->inboxIds());
    }

    public function test_pas_order_hidden_while_job_underway(): void
    {
        $order = $this->createOrder('pas');
        $this->partnerAcceptsAndStarts($order);

        $this->assertNotContains($order->order_id, $this->inboxIds());
    }

    public function test_pas_done_and_unpaid_appears_in_inbox(): void
    {
        $order = $this->createOrder('pas');
        $this->partnerAcceptsAndStarts($order);
        $this->markAllRequestsCompleted($order);

        $ids = $this->inboxIds();
        $this->assertContains($order->order_id, $ids);
    }

    public function test_cash_declared_surfaces_then_clears_after_confirm(): void
    {
        $order = $this->createOrder('pas');
        $this->partnerAcceptsAndStarts($order);
        $this->addCompletionDocument($order);

        Sanctum::actingAs($this->partner, ['*']);
        $this->patchJson("/api/v1/bookings/{$order->order_id}/complete", [
            'payment_received' => 'cash',
        ])->assertStatus(200)->assertJsonPath('success', true);

        // Surfaces as cash-pending until the customer confirms.
        $this->assertContains($order->order_id, $this->inboxIds());

        Sanctum::actingAs($this->customer, ['*']);
        $this->postJson("/api/v1/bookings/{$order->order_id}/confirm-cash")
            ->assertStatus(200)
            ->assertJsonPath('success', true);

        $this->assertTrue((bool) $order->refresh()->cash_confirmed);
        $this->assertNotContains($order->order_id, $this->inboxIds());
    }

    public function test_pay_now_issues_esewa_session_for_pas_order(): void
    {
        $order = $this->createOrder('pas');
        $this->partnerAcceptsAndStarts($order);
        $this->markAllRequestsCompleted($order);

        Sanctum::actingAs($this->customer, ['*']);
        $response = $this->postJson("/api/v1/bookings/retry-payment/{$order->order_id}")
            ->assertStatus(200)
            ->assertJsonPath('success', true);

        $payload = $response->json('payload');

        // The signature must verify against the exact posted strings.
        $expected = base64_encode(hash_hmac(
            'sha256',
            "total_amount={$payload['total_amount']},transaction_uuid={$payload['transaction_uuid']},product_code={$payload['product_code']}",
            config('payment.gateways.esewa.secret_key'),
            true
        ));
        $this->assertTrue(hash_equals($expected, $payload['signature']));
        $this->assertSame(
            config('payment.gateways.esewa.product_code', 'EPAYTEST'),
            $payload['product_code']
        );

        $this->assertNotNull(
            \App\Models\Payment::where('booking_id', $order->order_id)
                ->where('status', 'initiated')
                ->first()
        );
    }

    public function test_prepaid_completion_ignores_spoofed_cash_declaration(): void
    {
        Http::fake([
            '*api/epay/transaction/status*' => Http::response([
                'status' => 'COMPLETE',
                'ref_id' => 'REF-' . uniqid(),
                'total_amount' => '1695.00',
            ], 200),
        ]);

        $order = $this->createOrder('online');
        app(PaymentService::class)->verifyAndUpdatePayment('esewa', [
            'transaction_uuid' => $order->fresh()->transaction_uuid,
            'total_amount' => $order->total_amount,
        ]);
        $this->assertSame('paid', $order->refresh()->payment_status);

        $this->partnerAcceptsAndStarts($order);
        $this->addCompletionDocument($order);

        Sanctum::actingAs($this->partner, ['*']);
        $this->patchJson("/api/v1/bookings/{$order->order_id}/complete", [
            'payment_received' => 'cash', // spoofed - prepaid is always online
        ])->assertStatus(200)->assertJsonPath('success', true);

        $this->assertSame('online', $order->refresh()->payment_received);
    }
}
