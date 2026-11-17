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
use App\Models\Payment;
use App\Models\Service;
use App\Models\ServicePricing;
use App\Models\User;
use App\Services\PaymentService;

/**
 * A booking must never look "booked" before the money lands.
 *
 *  - Online (prepaid) bookings stay in 'created' until the gateway confirms
 *    the payment - even when the customer picked a preferred partner. The
 *    partner is only notified after the payment is verified.
 *  - The /bookings index must never return unpaid pre-payment orders in the
 *    upcoming list, no matter which statuses the client requests.
 */
class BookingUpcomingVisibilityTest extends TestCase
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

                // Platform/system account owning the success-ledger rows (user_id 1).
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

        // An explicit user_id=1 insert does not advance the Postgres sequence;
        // after a fresh migrate the next User::create would collide with id 1.
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

        $category = Category::create([
            'name' => 'Plumbing',
            'is_active' => true,
        ]);

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
            'services' => [
                ['service_id' => $this->service->service_id, 'quantity' => 1],
            ],
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

    private function upcomingIndex(): array
    {
        // Mirrors the FE's getUpcomingBookings() statuses list.
        $statuses = [
            'created', 'pending', 'pending_payment', 'awaiting_partner',
            'confirmed', 'partner_assigned', 'assigned', 'accepted',
            'in_progress', 'working', 'pending_reassignment',
        ];

        Sanctum::actingAs($this->customer, ['*']);
        $response = $this->getJson('/api/v1/bookings?statuses=' . implode(',', $statuses));
        $response->assertStatus(200);

        return collect($response->json('data') ?? [])->pluck('id')->all();
    }

    public function test_online_booking_with_preferred_partner_is_not_booked_before_payment(): void
    {
        $order = $this->createBooking('online', true);

        // The job is NOT live before the gateway money lands: the order must
        // stay in 'created' (unpaid) - not partner_assigned, not confirmed.
        $this->assertSame('created', $order->fresh()->status);
        $this->assertSame('pending', $order->fresh()->payment_status);

        // The service request keeps the chosen partner pre-attached (as
        // 'assigned'), but the booking must not surface anywhere yet.
        $request = $order->serviceRequests()->first();
        $this->assertSame($this->partner->user_id, $request->partner_id);

        // The upcoming filter (FE status list) may not return it.
        $this->assertNotContains($order->order_id, $this->upcomingIndex());

        // The default list (no filters) must not return it either.
        Sanctum::actingAs($this->customer, ['*']);
        $defaultResponse = $this->getJson('/api/v1/bookings');
        $defaultResponse->assertStatus(200);
        $this->assertNotContains($order->order_id, collect($defaultResponse->json('data.data') ?? [])->pluck('id')->all());
    }

    public function test_online_booking_becomes_booked_only_after_payment_lands(): void
    {
        $order = $this->createBooking('online', true);
        $payment = Payment::where('booking_id', $order->order_id)->first();
        $this->assertNotNull($payment);

        Http::fake([
            '*api/epay/transaction/status*' => Http::response([
                'status' => 'COMPLETE',
                'ref_id' => 'REF-1',
                'total_amount' => (string) $order->total_amount,
            ], 200),
        ]);

        $result = app(PaymentService::class)->verifyAndUpdatePayment('esewa', [
            'transaction_uuid' => $payment->txn_id,
            'total_amount' => $order->total_amount,
        ]);

        // Payment landed: only now does the order become partner_assigned.
        $this->assertTrue($result['success']);
        $this->assertSame('paid', $payment->fresh()->status);
        $this->assertSame('partner_assigned', $order->fresh()->status);
        $this->assertSame('paid', $order->fresh()->payment_status);

        // And only now does it show up in upcoming bookings.
        $this->assertContains($order->order_id, $this->upcomingIndex());
    }

    public function test_unpaid_created_pending_orders_never_surface_even_when_requested(): void
    {
        $unpaid = $this->createBooking('online', false, now()->addDay()->setTime(10, 0)->format('Y-m-d H:i'));
        $this->assertSame('created', $unpaid->fresh()->status);

        $paid = $this->createBooking('pas', false, now()->addDays(2)->format('Y-m-d H:i'));
        $this->assertSame('confirmed', $paid->fresh()->status);

        // Explicitly request the pre-payment statuses together with a real one:
        // only the confirmed order may be returned.
        Sanctum::actingAs($this->customer, ['*']);
        $response = $this->getJson('/api/v1/bookings?statuses=created,pending,pending_payment,confirmed');
        $response->assertStatus(200);

        $ids = collect($response->json('data') ?? [])->pluck('id')->all();
        $this->assertNotContains($unpaid->order_id, $ids);
        $this->assertContains($paid->order_id, $ids);
    }

    public function test_pas_booking_with_preferred_partner_is_still_booked_immediately(): void
    {
        $order = $this->createBooking('pas', true);

        // PAS is settled at the door: the assignment stays live immediately.
        $this->assertSame('partner_assigned', $order->fresh()->status);
        $this->assertContains($order->order_id, $this->upcomingIndex());
    }

    public function test_partner_rejection_moves_order_to_pending_reassignment_and_stays_visible(): void
    {
        $order = $this->createBooking('pas', true);
        $this->assertSame('partner_assigned', $order->fresh()->status);

        // Partner rejects the assignment: the order must not vanish - it goes
        // to pending_reassignment (real writer, visible to the customer).
        Sanctum::actingAs($this->partner, ['*']);
        $response = $this->patchJson('/api/v1/bookings/' . $order->order_id . '/reject-assignment', [
            'reason' => 'Unavailable',
        ]);
        $response->assertStatus(200);

        $this->assertSame('pending_reassignment', $order->fresh()->status);
        $this->assertContains($order->order_id, $this->upcomingIndex());
    }

    public function test_unpaid_online_order_visible_via_unpaid_endpoint_only(): void
    {
        $order = $this->createBooking('online', false);
        $this->assertSame('created', $order->fresh()->status);

        // Still hidden from the regular index (not a booked appointment).
        $this->assertNotContains($order->order_id, $this->upcomingIndex());

        // But reachable via the dedicated unpaid endpoint (FE "Pay Now" flow).
        Sanctum::actingAs($this->customer, ['*']);
        $response = $this->getJson('/api/v1/bookings/unpaid');
        $response->assertStatus(200);
        $this->assertContains($order->order_id, collect($response->json('data') ?? [])->pluck('id')->all());
    }
}