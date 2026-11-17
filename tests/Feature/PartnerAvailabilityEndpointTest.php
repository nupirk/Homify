<?php

namespace Tests\Feature;

use App\Models\PartnerAvailability;
use App\Models\PartnerProfile;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\CreatesApplication;
use Tests\TestCase;

/**
 * Partner self-service working hours: the endpoints the app's Availability
 * screen talks to, and the contract AvailabilityService depends on.
 */
class PartnerAvailabilityEndpointTest extends TestCase
{
    use CreatesApplication, RefreshDatabase;

    private User $partner;

    protected function setUp(): void
    {
        parent::setUp();

        $this->partner = User::create([
            'first_name' => 'Hours',
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
    }

    private function asPartner(): void
    {
        Sanctum::actingAs($this->partner, ['*']);
    }

    public function test_availability_requires_authentication(): void
    {
        $this->getJson('/api/v1/partner/availability')->assertStatus(401);
    }

    public function test_customers_cannot_read_or_write_partner_availability(): void
    {
        $customer = User::create([
            'first_name' => 'Some',
            'last_name' => 'Customer',
            'email' => fake()->unique()->safeEmail(),
            'phone' => '98' . rand(10000000, 99999999),
            'password' => bcrypt('password'),
            'user_type' => 'customer',
            'is_active' => true,
        ]);

        Sanctum::actingAs($customer, ['*']);

        $this->getJson('/api/v1/partner/availability')
            ->assertStatus(403)
            ->assertJsonPath('success', false);

        $this->putJson('/api/v1/partner/availability', [
            'days' => [[
                'date' => Carbon::today()->addDay()->toDateString(),
                'is_available' => true,
                'start_time' => '09:00',
                'end_time' => '17:00',
            ]],
        ])->assertStatus(403);
    }

    public function test_days_without_rows_report_the_platform_default_window(): void
    {
        $this->asPartner();

        $response = $this->getJson('/api/v1/partner/availability?days=3')->assertStatus(200);

        $days = $response->json('data.days');
        $this->assertCount(3, $days);
        $this->assertSame(Carbon::today()->toDateString(), $days[0]['date']);
        $this->assertTrue($days[0]['is_available']);
        $this->assertSame('09:00', $days[0]['start_time']);
        $this->assertSame('18:00', $days[0]['end_time']);
        $this->assertTrue($days[0]['is_default']);
    }

    public function test_saving_working_hours_persists_them(): void
    {
        $this->asPartner();
        $date = Carbon::today()->addDays(2)->toDateString();

        $this->putJson('/api/v1/partner/availability', [
            'days' => [[
                'date' => $date,
                'is_available' => true,
                'start_time' => '10:00',
                'end_time' => '16:00',
            ]],
        ])->assertStatus(200)->assertJsonPath('success', true);

        $this->assertDatabaseHas('partner_availability', [
            'partner_id' => $this->partner->user_id,
            'service_date' => $date,
            'booked' => false,
        ]);

        $day = collect($this->getJson('/api/v1/partner/availability?days=3')->json('data.days'))
            ->firstWhere('date', $date);

        $this->assertTrue($day['is_available']);
        $this->assertSame('10:00', $day['start_time']);
        $this->assertSame('16:00', $day['end_time']);
        $this->assertFalse($day['is_default']);
    }

    public function test_closing_a_day_does_not_fall_back_to_the_default_window(): void
    {
        $this->asPartner();
        $date = Carbon::today()->addDays(3)->toDateString();

        $this->putJson('/api/v1/partner/availability', [
            'days' => [[
                'date' => $date,
                'is_available' => false,
            ]],
        ])->assertStatus(200);

        $day = collect($this->getJson('/api/v1/partner/availability?days=5')->json('data.days'))
            ->firstWhere('date', $date);

        $this->assertFalse($day['is_available']);
        $this->assertNull($day['start_time']);
        $this->assertFalse($day['is_default']);

        // The zero-length sentinel row is what keeps the day closed.
        $this->assertDatabaseHas('partner_availability', [
            'partner_id' => $this->partner->user_id,
            'service_date' => $date,
            'start_time' => '00:00:00',
            'end_time' => '00:00:00',
        ]);
    }

    public function test_reopening_a_closed_day_removes_the_sentinel(): void
    {
        $this->asPartner();
        $date = Carbon::today()->addDays(4)->toDateString();

        $this->putJson('/api/v1/partner/availability', [
            'days' => [['date' => $date, 'is_available' => false]],
        ])->assertStatus(200);

        $this->putJson('/api/v1/partner/availability', [
            'days' => [[
                'date' => $date,
                'is_available' => true,
                'start_time' => '08:00',
                'end_time' => '12:00',
            ]],
        ])->assertStatus(200);

        $this->assertSame(
            1,
            PartnerAvailability::where('partner_id', $this->partner->user_id)
                ->where('service_date', $date)
                ->count()
        );

        $day = collect($this->getJson('/api/v1/partner/availability?days=6')->json('data.days'))
            ->firstWhere('date', $date);

        $this->assertTrue($day['is_available']);
        $this->assertSame('08:00', $day['start_time']);
        $this->assertSame('12:00', $day['end_time']);
    }

    public function test_reversed_working_hours_are_rejected(): void
    {
        $this->asPartner();

        $this->putJson('/api/v1/partner/availability', [
            'days' => [[
                'date' => Carbon::today()->addDay()->toDateString(),
                'is_available' => true,
                'start_time' => '17:00',
                'end_time' => '09:00',
            ]],
        ])->assertStatus(422)
            ->assertJsonPath('success', false);

        $this->assertStringContainsString(
            'must be after the start time',
            $this->putJson('/api/v1/partner/availability', [
                'days' => [[
                    'date' => Carbon::today()->addDay()->toDateString(),
                    'is_available' => true,
                    'start_time' => '17:00',
                    'end_time' => '09:00',
                ]],
            ])->json('message')
        );
    }

    public function test_past_dates_are_rejected(): void
    {
        $this->asPartner();

        $this->putJson('/api/v1/partner/availability', [
            'days' => [[
                'date' => Carbon::yesterday()->toDateString(),
                'is_available' => true,
                'start_time' => '09:00',
                'end_time' => '17:00',
            ]],
        ])->assertStatus(422);
    }

    public function test_saved_hours_drive_the_booking_engine(): void
    {
        $this->asPartner();
        $date = Carbon::today()->addDays(2);

        $this->putJson('/api/v1/partner/availability', [
            'days' => [[
                'date' => $date->toDateString(),
                'is_available' => true,
                'start_time' => '10:00',
                'end_time' => '14:00',
            ]],
        ])->assertStatus(200);

        $availability = app(\App\Services\AvailabilityService::class);

        $this->assertTrue($availability->isAvailable(
            $this->partner->user_id,
            $date->copy()->setTime(11, 0),
            60
        ));

        $outside = $availability->check($this->partner->user_id, $date->copy()->setTime(15, 0), 60);
        $this->assertFalse($outside['available']);
        $this->assertSame('outside_hours', $outside['reason']);
    }
}
