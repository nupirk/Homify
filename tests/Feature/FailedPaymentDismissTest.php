<?php

namespace Tests\Feature;

use App\Models\Address;
use App\Models\Category;
use App\Models\Order;
use App\Models\PartnerProfile;
use App\Models\Service;
use App\Models\ServicePricing;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\CreatesApplication;
use Tests\TestCase;

/**
 * A failed/expired online attempt is already cancelled by the failure path, so
 * cancel() refuses it and the row sat in "Awaiting Payment" for 24h with no
 * action that closed it. The customer can now dismiss it.
 */
class FailedPaymentDismissTest extends TestCase
{
    use CreatesApplication, RefreshDatabase;

    private User $customer;
    private User $otherCustomer;
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

        DB::statement(
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

        $this->otherCustomer = User::create([
            'first_name' => 'Other',
            'last_name' => 'Customer',
            'email' => fake()->unique()->safeEmail(),
            'phone' => '98' . rand(10000000, 99999999),
            'password' => bcrypt('password'),
            'user_type' => 'customer',
            'is_active' => true,
        ]);

        $this->partner = User::create([
            'first_name' => 'Test',
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
            'requires_prepay' => true,
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

    private function createFailedOrder(): Order
    {
        Sanctum::actingAs($this->customer, ['*']);

        $response = $this->postJson('/api/v1/bookings', [
            'services' => [
                ['service_id' => $this->service->service_id, 'quantity' => 1],
            ],
            'scheduled_at' => $this->scheduledAt,
            'address_id' => $this->address->address_id,
            'payment_method' => 'online',
            'preferred_partner_id' => $this->partner->user_id,
        ]);

        $response->assertStatus(201);

        $order = Order::findOrFail($response->json('order_id'));

        // Same path the app uses when the eSewa window is abandoned/fails.
        $this->postJson("/api/v1/bookings/{$order->order_id}/payment-failed")
            ->assertStatus(200)
            ->assertJsonPath('success', true);

        return $order->fresh();
    }

    private function unpaidIds(): array
    {
        return array_values(array_map(
            fn ($row) => $row['id'],
            $this->getJson('/api/v1/bookings/unpaid')->assertStatus(200)->json('data') ?? []
        ));
    }

    public function test_a_failed_payment_can_be_dismissed_and_leaves_awaiting_payment(): void
    {
        $order = $this->createFailedOrder();

        $this->assertSame('cancelled', $order->status);
        $this->assertSame('failed', $order->payment_status);
        $this->assertNull($order->payment_dismissed_at);

        // It is still reachable so the customer can retry the payment...
        $this->assertContains($order->order_id, $this->unpaidIds());

        // ...but cancel() must refuse it (already cancelled) - that refusal is
        // exactly why there was nothing to press before.
        Sanctum::actingAs($this->customer, ['*']);
        $this->patchJson("/api/v1/bookings/{$order->order_id}/cancel")
            ->assertStatus(400)
            ->assertJsonPath('success', false);

        $this->patchJson("/api/v1/bookings/{$order->order_id}/dismiss-payment")
            ->assertStatus(200)
            ->assertJsonPath('success', true);

        $order->refresh();
        $this->assertNotNull($order->payment_dismissed_at);
        $this->assertSame('cancelled', $order->status);

        $this->assertNotContains($order->order_id, $this->unpaidIds());
    }

    public function test_dismissing_an_unrolled_unpaid_order_cancels_it_first(): void
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

        $order = Order::findOrFail($response->json('order_id'));
        $this->assertSame('created', $order->status);
        $this->assertSame('pending', $order->payment_status);

        $this->patchJson("/api/v1/bookings/{$order->order_id}/dismiss-payment")
            ->assertStatus(200)
            ->assertJsonPath('success', true);

        $order->refresh();
        $this->assertNotNull($order->payment_dismissed_at);
        // CancellationService records who cancelled; either cancelled status
        // is already excluded from the unpaid list, the flag is what closes it.
        $this->assertSame('cancelled_by_customer', $order->status);
        $this->assertSame('failed', $order->payment_status);
        $this->assertCount(
            0,
            $order->serviceRequests()->whereIn('status', ['pending', 'assigned', 'accepted'])->get()
        );
        $this->assertNotContains($order->order_id, $this->unpaidIds());
    }

    public function test_only_the_owner_can_dismiss_and_never_a_paid_order(): void
    {
        $order = $this->createFailedOrder();

        Sanctum::actingAs($this->otherCustomer, ['*']);
        $this->patchJson("/api/v1/bookings/{$order->order_id}/dismiss-payment")
            ->assertStatus(403);
        $this->assertNull($order->fresh()->payment_dismissed_at);

        $order->update(['payment_status' => 'paid', 'status' => 'confirmed']);

        Sanctum::actingAs($this->customer, ['*']);
        $this->patchJson("/api/v1/bookings/{$order->order_id}/dismiss-payment")
            ->assertStatus(400)
            ->assertJsonPath('success', false);

        $this->assertNull($order->fresh()->payment_dismissed_at);
        $this->assertSame('confirmed', $order->fresh()->status);
    }
}
