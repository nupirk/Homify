<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\CreatesApplication;
use Tests\TestCase;
use App\Jobs\CancelExpiredBookings;
use App\Models\Address;
use App\Models\Category;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Service;
use App\Models\ServicePricing;
use App\Models\User;

/**
 * Expiry/cancellation of unpaid online bookings (the original root cause:
 * unpaid online bookings staying assignable forever).
 */
class BookingExpiryTest extends TestCase
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

    private function createOrder(string $paymentMethod, ?string $scheduledAt = null): Order
    {
        Sanctum::actingAs($this->customer, ['*']);
        $response = $this->postJson('/api/v1/bookings', [
            'services' => [
                ['service_id' => $this->service->service_id, 'quantity' => 1],
            ],
            'scheduled_at' => $scheduledAt ?? $this->scheduledAt,
            'address_id' => $this->address->address_id,
            'payment_method' => $paymentMethod,
        ]);
        $response->assertStatus(201);
        return Order::findOrFail($response->json('order_id'));
    }

    public function test_cancel_expired_bookings_cancels_unpaid_online_order_only(): void
    {
        $expiredOnline = $this->createOrder('online', now()->addDay()->addHour()->format('Y-m-d H:i'));
        $expiredOnline->update([
            'status' => 'pending_payment',
            'payment_status' => 'pending',
            'payment_expires_at' => now()->subMinutes(5),
        ]);
        $expiredPayment = Payment::create([
            'booking_id' => $expiredOnline->order_id,
            'gateway' => 'esewa',
            'amount' => $expiredOnline->total_amount,
            'status' => 'initiated',
            'expires_at' => now()->subMinutes(5),
        ]);

        // A PAS order has no payment deadline - must NEVER be cancelled.
        $pasOrder = $this->createOrder('pas', now()->addDay()->addHours(2)->format('Y-m-d H:i'));
        $pasOrder->update([
            'payment_expires_at' => null,
        ]);

        // A fresh online order within its window must survive.
        $freshOnline = $this->createOrder('online', now()->addDay()->addHours(3)->format('Y-m-d H:i'));
        $freshOnline->update([
            'status' => 'pending_payment',
            'payment_status' => 'pending',
            'payment_expires_at' => now()->addMinutes(5),
        ]);

        CancelExpiredBookings::dispatchSync();

        $this->assertSame('cancelled', $expiredOnline->refresh()->status);
        $this->assertSame('failed', $expiredOnline->payment_status);
        $this->assertSame('failed', $expiredPayment->refresh()->status);
        $this->assertSame('cancelled', $expiredOnline->serviceRequests()->first()?->status);

        $this->assertNotSame('cancelled', $pasOrder->refresh()->status);
        $this->assertNotSame('cancelled', $freshOnline->refresh()->status);
    }

    public function test_expire_old_payments_marks_initiated_payment_failed(): void
    {
        $order = $this->createOrder('online');
        $order->update([
            'status' => 'pending_payment',
            'payment_status' => 'pending',
            'payment_expires_at' => now()->subMinutes(5),
        ]);
        $payment = Payment::create([
            'booking_id' => $order->order_id,
            'gateway' => 'esewa',
            'amount' => $order->total_amount,
            'status' => 'initiated',
            'expires_at' => now()->subMinutes(5),
        ]);

        $this->artisan('payments:expire')
            ->expectsOutputToContain('Found 1 expired payment(s).')
            ->assertExitCode(0);

        $this->assertSame('failed', $payment->refresh()->status);
        $this->assertSame('failed', $order->refresh()->payment_status);
    }
}