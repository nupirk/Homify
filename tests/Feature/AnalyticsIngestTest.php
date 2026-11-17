<?php

namespace Tests\Feature;

use App\Models\AnalyticsEvent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\CreatesApplication;
use Tests\TestCase;

class AnalyticsIngestTest extends TestCase
{
    use CreatesApplication, RefreshDatabase;

    private User $customer;
    private User $admin;

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

        User::unguarded(fn () => $this->customer = User::create([
            'user_id' => 2,
            'first_name' => 'Cust',
            'last_name' => 'Omer',
            'email' => 'cust@example.com',
            'phone' => '9800000002',
            'password' => bcrypt('secret'),
            'user_type' => 'customer',
            'is_active' => true,
        ]));

        $this->admin = User::find(1);
    }

    public function test_public_ingest_accepts_events_without_auth(): void
    {
        $res = $this->postJson('/api/v1/analytics', [
            'session_id' => 'sess123',
            'platform' => 'android',
            'events' => [
                ['name' => 'app_open', 'ts' => 1700000000000],
                ['name' => 'service_view', 'props' => ['partner_id' => 7]],
            ],
        ]);

        $res->assertOk()->assertJson(['success' => true]);
        $this->assertSame(2, AnalyticsEvent::count());
        $this->assertNull(AnalyticsEvent::first()->user_id);
    }

    public function test_ingest_attaches_user_when_token_present(): void
    {
        Sanctum::actingAs($this->customer);

        $this->postJson('/api/v1/analytics', [
            'events' => [['name' => 'add_to_cart']],
        ])->assertOk();

        $this->assertSame($this->customer->user_id, AnalyticsEvent::first()->user_id);
    }

    public function test_ingest_rejects_empty_batch_and_oversized_payload(): void
    {
        $this->postJson('/api/v1/analytics', ['events' => []])->assertStatus(422);

        $events = array_fill(0, 51, ['name' => 'app_open']);
        $this->postJson('/api/v1/analytics', ['events' => $events])->assertStatus(422);
    }

    public function test_ingest_drops_events_outside_the_allow_list(): void
    {
        $res = $this->postJson('/api/v1/analytics', [
            'events' => [
                ['name' => 'app_open'],
                ['name' => 'definitely_not_a_real_event'],
                ['name' => 'promo_applied'],
            ],
        ]);

        $res->assertOk()
            ->assertJsonPath('accepted', 2)
            ->assertJsonPath('dropped', 1);

        $this->assertSame(
            ['app_open', 'promo_applied'],
            AnalyticsEvent::orderBy('id')->pluck('event')->all()
        );
    }

    public function test_funnel_requires_admin(): void
    {
        $this->getJson('/api/v1/admin/analytics/funnel')->assertStatus(401);

        Sanctum::actingAs($this->customer);
        $this->getJson('/api/v1/admin/analytics/funnel')->assertStatus(403);
    }

    public function test_funnel_reports_counts_and_conversions(): void
    {
        foreach (range(1, 10) as $i) {
            AnalyticsEvent::create(['event' => 'app_open', 'created_at' => now()]);
        }
        foreach (range(1, 5) as $i) {
            AnalyticsEvent::create(['event' => 'checkout_start', 'created_at' => now()]);
        }
        foreach (range(1, 5) as $i) {
            AnalyticsEvent::create(['event' => 'payment_initiated', 'created_at' => now()]);
        }
        foreach (range(1, 5) as $i) {
            AnalyticsEvent::create(['event' => 'payment_success', 'created_at' => now()]);
        }
        AnalyticsEvent::create(['event' => 'payment_failed', 'created_at' => now()]);

        Sanctum::actingAs($this->admin);
        $res = $this->getJson('/api/v1/admin/analytics/funnel?days=7');
        $res->assertOk();

        $data = $res->json('data');
        $byEvent = collect($data['funnel'])->keyBy('event');

        $this->assertSame(10, $byEvent['app_open']['count']);
        $this->assertSame(5, $byEvent['payment_success']['count']);
        $this->assertEquals(1.0, $byEvent['payment_success']['step_conversion']);
        $this->assertEquals(0.5, $byEvent['payment_success']['overall_conversion']);
        $this->assertEquals(0.833, $data['payment']['success_rate']);
    }
}
