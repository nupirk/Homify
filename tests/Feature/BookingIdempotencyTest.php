<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\CreatesApplication;
use Tests\TestCase;
use App\Models\Address;
use App\Models\Category;
use App\Models\Order;
use App\Models\Service;
use App\Models\ServicePricing;
use App\Models\User;

class BookingIdempotencyTest extends TestCase
{
    use CreatesApplication, RefreshDatabase;

    private User $customer;
    private Address $address;
    private Service $service;
    private string $scheduledAt;

    protected function setUp(): void
    {
        parent::setUp();

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
            'requires_prepay' => false, // PAS-eligible service
        ]);

        ServicePricing::create([
            'service_id' => $this->service->service_id,
            'tier_name' => 'Standard',
            'base_price' => 1000,
            'unit' => 'hour',
            'is_active' => true,
        ]);

        Sanctum::actingAs($this->customer, ['*']);

        $this->scheduledAt = now()->addDays(1)->format('Y-m-d H:i:s');
    }

    private function bookingPayload(): array
    {
        return [
            'services' => [
                ['service_id' => $this->service->service_id, 'quantity' => 1],
            ],
            'scheduled_at' => $this->scheduledAt,
            'address_id' => $this->address->address_id,
            'payment_method' => 'pas',
        ];
    }

    public function test_duplicate_booking_submission_returns_existing_order(): void
    {
        $first = $this->postJson('/api/v1/bookings', $this->bookingPayload());
        $first->assertStatus(201);

        $firstOrderId = $first->json('order_id');
        $this->assertNotNull($firstOrderId);

        // Immediate duplicate submission (simulates double-tap/retry)
        $second = $this->postJson('/api/v1/bookings', $this->bookingPayload());
        $second->assertStatus(201);
        $this->assertTrue($second->json('duplicate'));
        $this->assertSame($firstOrderId, $second->json('order_id'));

        $this->assertSame(1, Order::count(), 'Only one order should exist after duplicate submission');
    }

    public function test_identical_submission_after_window_creates_new_order(): void
    {
        $first = $this->postJson('/api/v1/bookings', $this->bookingPayload());
        $first->assertStatus(201);

        // Move the guard window: pretend the existing order is older than 60s
        Order::query()->update(['created_at' => now()->subMinutes(5)]);

        $second = $this->postJson('/api/v1/bookings', $this->bookingPayload());
        $second->assertStatus(201);
        $this->assertNull($second->json('duplicate'));
        $this->assertNotSame($first->json('order_id'), $second->json('order_id'));

        $this->assertSame(2, Order::count());
    }

    public function test_different_service_creates_separate_order(): void
    {
        $payload = $this->bookingPayload();

        $first = $this->postJson('/api/v1/bookings', $payload);
        $first->assertStatus(201);

        // Same user, same time, but different services
        $otherService = Service::create([
            'category_id' => Category::first()->category_id,
            'name' => 'Deep Cleaning',
            'description' => 'Deep cleaning service',
            'is_active' => true,
            'requires_prepay' => false, // PAS-eligible service
        ]);
        ServicePricing::create([
            'service_id' => $otherService->service_id,
            'tier_name' => 'Standard',
            'base_price' => 2000,
            'unit' => 'hour',
            'is_active' => true,
        ]);

        $payload['services'] = [
            ['service_id' => $otherService->service_id, 'quantity' => 1],
        ];

        $second = $this->postJson('/api/v1/bookings', $payload);
        $second->assertStatus(201);
        $this->assertNull($second->json('duplicate'));

        $this->assertSame(2, Order::count());
    }

    public function test_duplicate_retry_of_prepay_order_keeps_asking_for_payment(): void
    {
        // Prepay-required service: the booking must be online, not PAS.
        $this->service->update(['requires_prepay' => true]);

        $payload = $this->bookingPayload();
        $payload['payment_method'] = 'online';

        $first = $this->postJson('/api/v1/bookings', $payload);
        $first->assertStatus(201);
        $this->assertNotNull($first->json('payment_url'), 'Initial prepay booking must return the eSewa session');
        $this->assertNotNull($first->json('payload'));

        $firstOrderId = $first->json('order_id');
        $this->assertSame('online', Order::find($firstOrderId)->payment_method);

        // Immediate duplicate retry (double-tap after the WebView was closed):
        // the existing order is prepaid online, so the response must still
        // hand back the payment session - NEVER a "pay later" PAS message.
        $second = $this->postJson('/api/v1/bookings', $payload);
        $second->assertStatus(201);
        $this->assertTrue($second->json('duplicate'));
        $this->assertSame($firstOrderId, $second->json('order_id'));
        $this->assertSame('online', $second->json('payment_method'));
        $this->assertNotNull($second->json('payment_url'));
        $this->assertNotNull($second->json('payload'));
        $this->assertSame(
            1,
            Order::count(),
            'Only one order should exist after duplicate submission'
        );
    }

    public function test_duplicate_retry_of_pas_order_stays_pas(): void
    {
        // PAS-eligible service: the duplicate must confirm as PAS, never
        // demand a gateway payment.
        $payload = $this->bookingPayload(); // payment_method = pas

        $first = $this->postJson('/api/v1/bookings', $payload);
        $first->assertStatus(201);
        $this->assertSame('pas', $first->json('payment_method'));

        $firstOrderId = $first->json('order_id');

        // Even a duplicate sent with an "online" method must not flip the
        // existing PAS order into a gateway payment.
        $payload['payment_method'] = 'online';
        $second = $this->postJson('/api/v1/bookings', $payload);
        $second->assertStatus(201);
        $this->assertTrue($second->json('duplicate'));
        $this->assertSame($firstOrderId, $second->json('order_id'));
        $this->assertSame('pas', $second->json('payment_method'));
        $this->assertNull($second->json('payment_url'));
    }
}
