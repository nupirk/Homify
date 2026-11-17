<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\CreatesApplication;
use Tests\TestCase;
use App\Models\Address;
use App\Models\Category;
use App\Models\Order;
use App\Models\PartnerProfile;
use App\Models\Service;
use App\Models\ServicePricing;
use App\Models\User;

/**
 * Payment gate on partner assignment.
 *
 * An online-prepay order is only a real job once the gateway payment lands:
 * auto-assign, the partner job list, accept and admin reassignments must all
 * refuse to promote/notify unpaid online bookings.
 */
class AssignmentPaymentGateTest extends TestCase
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
            'email' => 'platform@urban.local',
            'phone' => '9800000001',
            'password' => bcrypt('secret'),
            'user_type' => 'admin',
            'is_active' => true,
        ]));

        DB::statement(
            "SELECT setval('users_user_id_seq', GREATEST((SELECT COALESCE(MAX(user_id), 1) FROM users), 1))"
        );

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

    public function test_auto_assign_skips_unpaid_online_orders(): void
    {
        $unpaid = $this->createBooking('online', false, now()->addDay()->setTime(10, 0)->format('Y-m-d H:i'));
        $pas = $this->createBooking('pas', false, now()->addDays(2)->setTime(10, 0)->format('Y-m-d H:i'));

        $this->assertSame('created', $unpaid->fresh()->status);
        $this->assertSame('confirmed', $pas->fresh()->status);

        $this->artisan('bookings:auto-assign')->assertSuccessful();

        // PAS (settled at the door) gets auto-assigned.
        $pasRequest = $pas->serviceRequests()->first();
        $this->assertSame('assigned', $pasRequest->fresh()->status);
        $this->assertNotNull($pasRequest->fresh()->partner_id);
        $this->assertSame('partner_assigned', $pas->fresh()->status);

        // Unpaid online-prepay must be left untouched: still pending, no
        // partner, order still created (never "booked").
        $unpaidRequest = $unpaid->serviceRequests()->first();
        $this->assertSame('pending', $unpaidRequest->fresh()->status);
        $this->assertNull($unpaidRequest->fresh()->partner_id);
        $this->assertSame('created', $unpaid->fresh()->status);
    }

    public function test_auto_assign_assigns_paid_online_orders(): void
    {
        $paid = $this->createBooking('online', false, now()->addDay()->setTime(10, 0)->format('Y-m-d H:i'));
        $paid->update(['payment_status' => 'paid']);

        $this->artisan('bookings:auto-assign')->assertSuccessful();

        $paidRequest = $paid->serviceRequests()->first();
        $this->assertSame('assigned', $paidRequest->fresh()->status);
        $this->assertNotNull($paidRequest->fresh()->partner_id);
        $this->assertSame('partner_assigned', $paid->fresh()->status);
    }

    public function test_partner_cannot_accept_unpaid_online_booking(): void
    {
        // Preferred partner: the partner is remembered, but the request stays
        // 'pending' (not booked) until the gateway settles.
        $order = $this->createBooking('online', true);
        $this->assertSame('pending', $order->serviceRequests()->first()->fresh()->status);
        $this->assertSame($this->partner->user_id, $order->serviceRequests()->first()->partner_id);

        Sanctum::actingAs($this->partner, ['*']);
        $this->patchJson("/api/v1/bookings/{$order->order_id}/accept")
            ->assertStatus(409)
            ->assertJsonPath('payment_required', true);

        // The unpaid order is still not booked.
        $this->assertSame('created', $order->fresh()->status);
        $this->assertSame('pending', $order->serviceRequests()->first()->fresh()->status);

        // Once the gateway money lands, PaymentService promotes the request to
        // 'assigned' (simulated here) and acceptance is allowed.
        $order->update(['payment_status' => 'paid']);
        $order->serviceRequests()->update(['status' => 'assigned']);

        $this->patchJson("/api/v1/bookings/{$order->order_id}/accept")
            ->assertStatus(200)
            ->assertJsonPath('success', true);

        $this->assertSame('confirmed', $order->fresh()->status);
    }

    public function test_unpaid_online_job_is_hidden_from_partner_job_list(): void
    {
        $unpaid = $this->createBooking('online', true);
        $pas = $this->createBooking('pas', true, now()->addDays(2)->setTime(10, 0)->format('Y-m-d H:i'));

        Sanctum::actingAs($this->partner, ['*']);
        $response = $this->getJson('/api/v1/bookings/assigned');
        $response->assertStatus(200);

        $ids = collect($response->json('data') ?? [])->pluck('id')->all();

        // The paid/live PAS job shows; the unpaid online booking does not.
        $this->assertContains($pas->order_id, $ids);
        $this->assertNotContains($unpaid->order_id, $ids);
    }

    public function test_admin_reassign_blocked_for_unpaid_online_booking(): void
    {
        $order = $this->createBooking('online', false);

        $admin = User::where('user_type', 'admin')->firstOrFail();
        Sanctum::actingAs($admin, ['*']);
        $this->patchJson("/api/v1/bookings/{$order->order_id}/reassign", [
            'partner_id' => $this->partner->user_id,
            'reason' => 'test',
        ])->assertStatus(409);

        $this->assertSame('created', $order->fresh()->status);
    }

    public function test_customer_cannot_reassign_a_booking(): void
    {
        $order = $this->createBooking('online', false);

        Sanctum::actingAs($this->customer, ['*']);
        $this->patchJson("/api/v1/bookings/{$order->order_id}/reassign", [
            'partner_id' => $this->partner->user_id,
            'reason' => 'test',
        ])->assertStatus(403);

        Sanctum::actingAs($this->customer, ['*']);
        $this->patchJson("/api/v1/bookings/{$order->order_id}/assign-partner", [
            'partner_id' => $this->partner->user_id,
        ])->assertStatus(403);

        $this->assertSame('created', $order->fresh()->status);
    }

    public function test_revert_command_cleans_up_ghost_bookings(): void
    {
        // Simulate a pre-fix ghost: unpaid online order wrongly promoted to
        // partner_assigned with a partner attached to the service request.
        $order = $this->createBooking('online', false);
        $order->update(['status' => 'partner_assigned']);
        $order->serviceRequests()->first()->update([
            'status' => 'assigned',
            'partner_id' => $this->partner->user_id,
            'partner_assigned_at' => now(),
            'assignment_expires_at' => now()->addMinutes(5),
        ]);

        $this->artisan('bookings:revert-unpaid-assignments')->assertSuccessful();

        $order->refresh();
        $this->assertSame('created', $order->status);

        $request = $order->serviceRequests()->first()->fresh();
        $this->assertSame('pending', $request->status);
        $this->assertNull($request->partner_id);
        $this->assertNull($request->assignment_expires_at);
    }
}