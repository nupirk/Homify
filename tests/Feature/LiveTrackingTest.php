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
use Laravel\Sanctum\Sanctum;
use Tests\CreatesApplication;
use Tests\TestCase;

/**
 * Live tracking contract:
 *  - partners heartbeat coordinates (Nepal bounds) while jobs are active
 *  - customers poll GET bookings/{id}/tracking for status + fresh partner
 *    coords + destination; stale (>120s) or non-active jobs expose no coords
 *  - tracking is owner-only (404 for everyone else)
 */
class LiveTrackingTest extends TestCase
{
    use CreatesApplication, RefreshDatabase;

    private User $customer;
    private User $otherCustomer;
    private User $partner;
    private Address $address;
    private Service $service;
    private Order $order;

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
            'first_name' => 'Track',
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
            'first_name' => 'Track',
            'last_name' => 'Partner',
            'email' => fake()->unique()->safeEmail(),
            'phone' => '98' . rand(10000000, 99999999),
            'password' => bcrypt('password'),
            'user_type' => 'partner',
            'is_active' => true,
        ]);

        PartnerProfile::unguarded(fn () => PartnerProfile::create([
            'partner_id' => $this->partner->user_id,
            'status' => 'active',
        ]));

        $this->address = Address::create([
            'user_id' => $this->customer->user_id,
            'address_line_1' => 'Track Street',
            'city' => 'Kathmandu',
            'state' => 'Bagmati',
            'postal_code' => '44600',
            'country' => 'Nepal',
            'latitude' => 27.7172,
            'longitude' => 85.3240,
            'is_default' => true,
        ]);

        $category = Category::create(['name' => 'Plumbing', 'is_active' => true]);
        $this->service = Service::create([
            'category_id' => $category->category_id,
            'name' => 'Plumbing Repair',
            'description' => 'Plumbing repair service',
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

        // PAS booking with a preferred partner -> order partner_assigned,
        // service request assigned to our partner. Money settles at the door.
        Sanctum::actingAs($this->customer, ['*']);
        $response = $this->postJson('/api/v1/bookings', [
            'services' => [['service_id' => $this->service->service_id, 'quantity' => 1]],
            'scheduled_at' => now()->addDay()->setTime(10, 0)->format('Y-m-d H:i'),
            'address_id' => $this->address->address_id,
            'payment_method' => 'pas',
            'preferred_partner_id' => $this->partner->user_id,
        ]);
        $response->assertStatus(201);
        $this->order = Order::findOrFail($response->json('order_id'));
    }

    public function test_partner_heartbeat_updates_live_location(): void
    {
        Sanctum::actingAs($this->partner, ['*']);

        $this->postJson('/api/v1/partner/location', [
            'latitude' => 27.7000,
            'longitude' => 85.3100,
        ])->assertOk()->assertJson(['success' => true]);

        $profile = $this->partner->partnerProfile->fresh();
        $this->assertEquals(27.7, (float) $profile->live_lat);
        $this->assertEquals(85.31, (float) $profile->live_lng);
        $this->assertNotNull($profile->live_updated_at);
    }

    public function test_heartbeat_rejects_non_partners_and_bad_coordinates(): void
    {
        Sanctum::actingAs($this->customer, ['*']);
        $this->postJson('/api/v1/partner/location', [
            'latitude' => 27.7,
            'longitude' => 85.31,
        ])->assertStatus(403);

        Sanctum::actingAs($this->partner, ['*']);
        // Missing coordinates.
        $this->postJson('/api/v1/partner/location', [])->assertStatus(422);
        // Outside Nepal bounds (Kathmandu is ~27.7/85.3; London is not).
        $this->postJson('/api/v1/partner/location', [
            'latitude' => 51.5074,
            'longitude' => -0.1278,
        ])->assertStatus(422);

        $this->getJson('/api/v1/partner/location')->assertStatus(405);
    }

    public function test_tracking_requires_authentication(): void
    {
        // setUp authenticated this test instance for booking creation - drop
        // the guard so the request really goes out unauthenticated.
        app('auth')->forgetGuards();

        $this->getJson('/api/v1/bookings/' . $this->order->order_id . '/tracking')
            ->assertStatus(401);
    }

    public function test_tracking_is_owner_only(): void
    {
        Sanctum::actingAs($this->otherCustomer, ['*']);
        $this->getJson('/api/v1/bookings/' . $this->order->order_id . '/tracking')
            ->assertStatus(404);
    }

    public function test_tracking_returns_fresh_partner_location_and_destination(): void
    {
        // Partner is en route right now.
        Sanctum::actingAs($this->partner, ['*']);
        $this->postJson('/api/v1/partner/location', [
            'latitude' => 27.7050,
            'longitude' => 85.3200,
        ])->assertOk();

        Sanctum::actingAs($this->customer, ['*']);
        $res = $this->getJson('/api/v1/bookings/' . $this->order->order_id . '/tracking');
        $res->assertOk();

        $data = $res->json('data');
        $this->assertSame($this->order->order_id, $data['order_id']);
        $this->assertSame('partner_assigned', $data['status']);
        $this->assertNotNull($data['partner']);
        $this->assertSame($this->partner->user_id, $data['partner']['partner_id']);

        $this->assertNotNull($data['partner_location']);
        $this->assertEquals(27.705, $data['partner_location']['latitude']);
        $this->assertEquals(85.32, $data['partner_location']['longitude']);

        $this->assertNotNull($data['destination']);
        $this->assertEquals(27.7172, $data['destination']['latitude']);
        $this->assertEquals(85.324, $data['destination']['longitude']);
    }

    public function test_tracking_hides_stale_location(): void
    {
        $profile = $this->partner->partnerProfile;
        $profile->update([
            'live_lat' => 27.705,
            'live_lng' => 85.32,
            'live_updated_at' => now()->subMinutes(5),
        ]);

        Sanctum::actingAs($this->customer, ['*']);
        $data = $this->getJson('/api/v1/bookings/' . $this->order->order_id . '/tracking')
            ->assertOk()
            ->json('data');

        $this->assertNull($data['partner_location']);
        // Status + destination still reported.
        $this->assertSame('partner_assigned', $data['status']);
        $this->assertNotNull($data['destination']);
    }

    public function test_tracking_hides_location_for_finished_orders(): void
    {
        $profile = $this->partner->partnerProfile;
        $profile->update([
            'live_lat' => 27.705,
            'live_lng' => 85.32,
            'live_updated_at' => now(),
        ]);

        $this->order->update(['status' => 'completed']);

        Sanctum::actingAs($this->customer, ['*']);
        $data = $this->getJson('/api/v1/bookings/' . $this->order->order_id . '/tracking')
            ->assertOk()
            ->json('data');

        $this->assertSame('completed', $data['status']);
        $this->assertNull($data['partner_location']);
    }

    public function test_tracking_hides_location_when_partner_never_heartbeated(): void
    {
        Sanctum::actingAs($this->customer, ['*']);
        $data = $this->getJson('/api/v1/bookings/' . $this->order->order_id . '/tracking')
            ->assertOk()
            ->json('data');

        $this->assertNull($data['partner_location']);
        $this->assertNotNull($data['partner']);
    }
}
