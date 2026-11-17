<?php

namespace Tests\Feature;

use App\Models\Address;
use App\Models\Category;
use App\Models\PartnerAvailability;
use App\Models\PartnerProfile;
use App\Models\Service;
use App\Models\ServicePricing;
use App\Models\User;
use App\Services\AvailabilityService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\CreatesApplication;
use Tests\TestCase;

/**
 * Partner availability is the contract between search, checkout and the
 * auto-assign cron: all three must agree on who is free at a given time.
 */
class PartnerAvailabilityTest extends TestCase
{
    use CreatesApplication, RefreshDatabase;

    private User $customer;
    private User $partner;
    private Address $address;
    private Service $service;
    private string $date;

    protected function setUp(): void
    {
        parent::setUp();

        $this->customer = User::create([
            'first_name' => 'Test', 'last_name' => 'Customer',
            'email' => fake()->unique()->safeEmail(),
            'phone' => '98' . rand(10000000, 99999999),
            'password' => bcrypt('password'), 'user_type' => 'customer', 'is_active' => true,
        ]);

        $this->partner = User::create([
            'first_name' => 'Test', 'last_name' => 'Partner',
            'email' => fake()->unique()->safeEmail(),
            'phone' => '98' . rand(10000000, 99999999),
            'password' => bcrypt('password'), 'user_type' => 'partner', 'is_active' => true,
        ]);

        PartnerProfile::unguarded(function () {
            PartnerProfile::create([
                'partner_id' => $this->partner->user_id,
                'status' => 'active',
            ]);
        });

        $this->address = Address::create([
            'user_id' => $this->customer->user_id,
            'address_line_1' => 'Test Street', 'city' => 'Kathmandu',
            'state' => 'Bagmati', 'postal_code' => '44600', 'country' => 'Nepal',
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
            'service_id' => $this->service->service_id,
            'tier_name' => 'standard',
            'base_price' => 1500,
            'estimated_duration_min' => 60,
            'is_active' => true,
        ]);

        $this->partner->partnerProfile->services()->attach($this->service->service_id);

        $this->date = now()->addDays(3)->format('Y-m-d');
    }

    private function at(string $time): \Carbon\Carbon
    {
        return \Carbon\Carbon::parse("{$this->date} {$time}");
    }

    public function test_partner_without_rows_uses_default_working_hours(): void
    {
        $availability = app(AvailabilityService::class);

        $this->assertTrue($availability->isAvailable($this->partner->user_id, $this->at('10:00'), $this->service->service_id));
        $this->assertTrue($availability->isAvailable($this->partner->user_id, $this->at('17:00'), $this->service->service_id));
        $this->assertFalse($availability->isAvailable($this->partner->user_id, $this->at('19:00'), $this->service->service_id));
        $this->assertSame('outside_hours', $availability->check($this->partner->user_id, $this->at('19:00'), 60)['reason']);
    }

    public function test_overlapping_booking_blocks_the_slot(): void
    {
        $this->bookAt('10:00');

        $availability = app(AvailabilityService::class);

        // 10:30-11:30 overlaps the existing 10:00-11:00 job.
        $this->assertSame('conflict', $availability->check($this->partner->user_id, $this->at('10:30'), 60)['reason']);

        // 11:00 starts exactly when the first job ends.
        $this->assertTrue($availability->isAvailable($this->partner->user_id, $this->at('11:00'), $this->service->service_id));
    }

    public function test_booked_availability_window_blocks_the_day(): void
    {
        PartnerAvailability::create([
            'partner_id' => $this->partner->user_id,
            'service_date' => $this->date,
            'start_time' => '09:00',
            'end_time' => '18:00',
            'booked' => true,
        ]);

        $availability = app(AvailabilityService::class);

        $this->assertFalse($availability->isAvailable($this->partner->user_id, $this->at('10:00'), $this->service->service_id));
    }

    public function test_checkout_rejects_a_partner_with_an_overlapping_job(): void
    {
        $this->bookAt('10:00');

        Sanctum::actingAs($this->customer, ['*']);

        $this->postJson('/api/v1/bookings', [
            'services' => [['service_id' => $this->service->service_id, 'quantity' => 1]],
            'scheduled_at' => "{$this->date} 10:30",
            'address_id' => $this->address->address_id,
            'preferred_partner_id' => $this->partner->user_id,
            'payment_method' => 'pas',
        ])->assertStatus(400)->assertJsonPath('reason', 'conflict');
    }

    public function test_checkout_accepts_the_same_partner_after_the_first_job_ends(): void
    {
        $this->bookAt('10:00');

        Sanctum::actingAs($this->customer, ['*']);

        $this->postJson('/api/v1/bookings', [
            'services' => [['service_id' => $this->service->service_id, 'quantity' => 1]],
            'scheduled_at' => "{$this->date} 11:00",
            'address_id' => $this->address->address_id,
            'preferred_partner_id' => $this->partner->user_id,
            'payment_method' => 'pas',
        ])->assertStatus(201);
    }

    public function test_search_filters_partners_who_are_busy_at_the_requested_slot(): void
    {
        $this->bookAt('10:00');

        Sanctum::actingAs($this->customer, ['*']);

        $busy = $this->getJson('/api/v1/partners/search?' . http_build_query([
            'service_ids' => (string) $this->service->service_id,
            'scheduled_at' => "{$this->date} 10:30",
        ]));
        $busy->assertStatus(200);
        $this->assertSame([], $busy->json('data'));

        $free = $this->getJson('/api/v1/partners/search?' . http_build_query([
            'service_ids' => (string) $this->service->service_id,
            'scheduled_at' => "{$this->date} 11:00",
        ]));
        $free->assertStatus(200);
        $this->assertCount(1, $free->json('data'));
        $this->assertTrue($free->json('data.0.available_for_slot'));
    }

    private function bookAt(string $time): void
    {
        Sanctum::actingAs($this->customer, ['*']);

        $this->postJson('/api/v1/bookings', [
            'services' => [['service_id' => $this->service->service_id, 'quantity' => 1]],
            'scheduled_at' => "{$this->date} {$time}",
            'address_id' => $this->address->address_id,
            'preferred_partner_id' => $this->partner->user_id,
            'payment_method' => 'pas',
        ])->assertStatus(201);
    }
}
