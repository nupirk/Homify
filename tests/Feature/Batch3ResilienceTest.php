<?php

namespace Tests\Feature;

use App\Jobs\SendPushNotification;
use App\Models\Address;
use App\Models\Category;
use App\Models\Order;
use App\Models\PartnerProfile;
use App\Models\Service;
use App\Models\ServicePricing;
use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\Sanctum;
use Tests\CreatesApplication;
use Tests\TestCase;

/**
 * Platform resilience hardening:
 *  - the api group is actually throttled (the limiter existed but was never wired)
 *  - the admin web login is brute-force limited
 *  - /api/v1/health reports real dependency status
 *  - accept/assign are not repeatable (status guard enforced under a lock)
 *  - db:backup degrades cleanly when pg_dump is unavailable
 */
class Batch3ResilienceTest extends TestCase
{
    use CreatesApplication, RefreshDatabase;

    private User $admin;
    private User $customer;
    private User $partner;
    private Address $address;
    private Service $service;
    private string $scheduledAt;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::unguarded(fn () => User::create([
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
            'category_id' => $category->category_id,
            'service_id' => $this->service->service_id,
            'tier_name' => 'standard',
            'base_price' => 1500,
            'is_active' => true,
        ]);

        $this->partner->partnerProfile->services()->attach($this->service->service_id);

        $this->scheduledAt = now()->addDay()->setTime(10, 0)->format('Y-m-d H:i');
    }

    private function createBooking(string $paymentMethod, bool $withPreferredPartner, ?string $scheduledAt = null): Order
    {
        Sanctum::actingAs($this->customer, ['*']);

        $payload = [
            'services' => [['service_id' => $this->service->service_id, 'quantity' => 1]],
            'scheduled_at' => $scheduledAt ?? $this->scheduledAt,
            'address_id' => $this->address->address_id,
            'payment_method' => $paymentMethod,
        ];

        if ($withPreferredPartner) {
            $payload['preferred_partner_id'] = $this->partner->user_id;
        }

        $response = $this->postJson('/api/v1/bookings', $payload);
        $response->assertStatus(201);

        return Order::findOrFail($response->json('order_id'));
    }

    public function test_api_middleware_group_applies_throttling(): void
    {
        $groups = app('router')->getMiddlewareGroups();

        $this->assertContains(
            ThrottleRequests::class . ':api',
            $groups['api'],
            'The api group must run throttle:api or nothing is rate limited.'
        );
    }

    public function test_api_requests_are_rate_limited(): void
    {
        config(['app.api_rate_limit' => 3]);
        // Unique IP so the shared array cache counter is ours alone.
        $this->withServerVariables(['REMOTE_ADDR' => '10.10.10.1']);

        $this->getJson('/api/v1/test')->assertOk();
        $this->getJson('/api/v1/test')->assertOk();
        $this->getJson('/api/v1/test')->assertOk();
        $this->getJson('/api/v1/test')->assertStatus(429);
    }

    public function test_admin_login_is_brute_force_limited(): void
    {
        // CSRF would reject the POST before the throttle runs; disable it so we
        // can prove the 5/min limiter kicks in.
        $this->withoutMiddleware(VerifyCsrfToken::class);
        $this->withServerVariables(['REMOTE_ADDR' => '10.10.10.2']);

        for ($i = 0; $i < 5; $i++) {
            $response = $this->post('/admin/login', ['email' => 'nobody@homify.test', 'password' => 'wrong']);
            $this->assertNotSame(429, $response->getStatusCode(), "Attempt {$i} should not be throttled yet");
        }

        $this->post('/admin/login', ['email' => 'nobody@homify.test', 'password' => 'wrong'])
            ->assertStatus(429);
    }

    public function test_health_endpoint_reports_dependency_status(): void
    {
        $this->getJson('/api/v1/health')
            ->assertOk()
            ->assertJsonPath('status', 'ok')
            ->assertJsonPath('checks.database.status', 'ok')
            ->assertJsonPath('checks.cache.status', 'ok')
            ->assertJsonPath('checks.storage.status', 'ok');
    }

    public function test_accepting_the_same_job_twice_is_rejected(): void
    {
        $order = $this->createBooking('pas', true, now()->addDays(2)->setTime(10, 0)->format('Y-m-d H:i'));

        Sanctum::actingAs($this->partner, ['*']);

        $this->patchJson("/api/v1/bookings/{$order->order_id}/accept")
            ->assertStatus(200)
            ->assertJsonPath('success', true);

        // Second accept must not silently re-promote the order.
        $this->patchJson("/api/v1/bookings/{$order->order_id}/accept")
            ->assertStatus(400);

        $this->assertSame('confirmed', $order->fresh()->status);
    }

    public function test_assigning_the_same_job_twice_is_rejected(): void
    {
        $order = $this->createBooking('pas', false, now()->addDays(2)->setTime(10, 0)->format('Y-m-d H:i'));

        Sanctum::actingAs($this->admin, ['*']);

        $this->patchJson("/api/v1/bookings/{$order->order_id}/assign-partner", [
            'partner_id' => $this->partner->user_id,
        ])->assertStatus(200);

        $this->patchJson("/api/v1/bookings/{$order->order_id}/assign-partner", [
            'partner_id' => $this->partner->user_id,
        ])->assertStatus(400);
    }

    public function test_assigning_a_partner_records_the_funnel_event(): void
    {
        $order = $this->createBooking('pas', false, now()->addDays(2)->setTime(10, 0)->format('Y-m-d H:i'));

        Sanctum::actingAs($this->admin);

        $this->patchJson("/api/v1/bookings/{$order->order_id}/assign-partner", [
            'partner_id' => $this->partner->user_id,
        ])->assertStatus(200);

        // Assignment is recorded server-side so the funnel does not depend on
        // the client (or the partner) reporting it.
        $this->assertDatabaseHas('analytics_events', [
            'event' => 'partner_assigned',
            'platform' => 'server',
        ]);
    }

    public function test_audit_entries_capture_request_context(): void
    {
        $this->createBooking('pas', false, now()->addDays(2)->setTime(10, 0)->format('Y-m-d H:i'));

        $log = DB::table('audit_logs')->where('action', 'booking_created')->first();

        $this->assertNotNull($log, 'Creating a booking must write an audit row');
        $this->assertNotNull($log->ip_address, 'Audit rows must record the client IP');
    }

    public function test_backup_command_fails_cleanly_without_a_dumper(): void
    {
        $directory = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'homify-backup-test-' . uniqid();
        config(['app.pg_dump_binary' => 'definitely-not-a-real-pg-dump-binary']);

        $this->artisan('db:backup', ['--path' => $directory])->assertExitCode(1);

        // No half-written archive is left behind to look like a real backup.
        $dumps = File::exists($directory) ? File::glob($directory . DIRECTORY_SEPARATOR . '*.dump') : [];
        $this->assertEmpty($dumps, 'A failed backup must not leave a partial archive');

        if (File::exists($directory)) {
            File::deleteDirectory($directory);
        }
    }

    public function test_push_delivery_can_be_queued_opt_in(): void
    {
        Queue::fake();
        config(['notifications.queue_push' => true]);

        $notification = NotificationService::sendWithPush(
            $this->customer->user_id,
            'booking_confirmed',
            'Booking confirmed',
            'Your booking is confirmed.'
        );

        // The in-app row is still written synchronously for the feed.
        $this->assertNotNull($notification);
        Queue::assertPushed(
            SendPushNotification::class,
            fn ($job) => $job->userId === $this->customer->user_id
        );
    }
}
