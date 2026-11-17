<?php

namespace Tests\Feature;

use App\Models\Address;
use App\Models\Category;
use App\Models\Order;
use App\Models\PartnerProfile;
use App\Models\Payment;
use App\Models\Service;
use App\Models\ServicePricing;
use App\Models\User;
use App\Services\PaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\CreatesApplication;
use Tests\TestCase;

/**
 * A failed/abandoned eSewa attempt must not leave a live booking behind, and
 * retrying it must restore every service request so a successful payment can
 * settle the order properly.
 */
class PaymentFailureRecoveryTest extends TestCase
{
    use CreatesApplication, RefreshDatabase;

    private User $customer;
    private User $partner;
    private Address $address;
    private Service $serviceA;
    private Service $serviceB;
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

        $this->serviceA = Service::create([
            'category_id' => $category->category_id,
            'name' => 'Home Cleaning',
            'description' => 'Standard home cleaning service',
            'is_active' => true,
            'requires_prepay' => true,
        ]);

        $this->serviceB = Service::create([
            'category_id' => $category->category_id,
            'name' => 'Deep Cleaning',
            'description' => 'Deep cleaning add-on',
            'is_active' => true,
            'requires_prepay' => true,
        ]);

        foreach ([$this->serviceA, $this->serviceB] as $service) {
            ServicePricing::create([
                'category_id' => $category->category_id,
                'service_id' => $service->service_id,
                'tier_name' => 'standard',
                'base_price' => 1500,
                'is_active' => true,
            ]);

            $this->partner->partnerProfile->services()->attach($service->service_id);
        }

        $this->scheduledAt = now()->addDay()->setTime(10, 0)->format('Y-m-d H:i');
    }

    private function createOnlineOrder(): Order
    {
        Sanctum::actingAs($this->customer, ['*']);

        $response = $this->postJson('/api/v1/bookings', [
            'services' => [
                ['service_id' => $this->serviceA->service_id, 'quantity' => 1],
                ['service_id' => $this->serviceB->service_id, 'quantity' => 1],
            ],
            'scheduled_at' => $this->scheduledAt,
            'address_id' => $this->address->address_id,
            'payment_method' => 'online',
            'preferred_partner_id' => $this->partner->user_id,
        ]);

        $response->assertStatus(201);

        return Order::findOrFail($response->json('order_id'));
    }

    public function test_failed_payment_cancels_every_request_and_retry_restores_them(): void
    {
        $order = $this->createOnlineOrder();

        $this->assertSame('created', $order->fresh()->status);
        $this->assertSame('pending', $order->fresh()->payment_status);
        $this->assertCount(2, $order->serviceRequests);
        foreach ($order->serviceRequests as $request) {
            $this->assertSame('pending', $request->status);
            $this->assertSame($this->partner->user_id, $request->partner_id);
        }

        // The app reports the abandoned gateway session (eSewa failure URLs
        // often lack transaction_uuid, so the webhook alone cannot find it).
        $this->postJson("/api/v1/bookings/{$order->order_id}/payment-failed")
            ->assertStatus(200)
            ->assertJsonPath('success', true);

        $order->refresh();
        $this->assertSame('cancelled', $order->status);
        $this->assertSame('failed', $order->payment_status);
        $this->assertCount(2, $order->serviceRequests()->where('status', 'cancelled')->get());
        $this->assertSame('failed', Payment::where('booking_id', $order->order_id)->latest('id')->first()->status);

        // Retry must restore every request (not just the first) and extend
        // the order deadline so the expiry job cannot kill the new session.
        $this->postJson("/api/v1/bookings/retry-payment/{$order->order_id}")
            ->assertStatus(200)
            ->assertJsonPath('success', true);

        $order->refresh();
        $this->assertSame('created', $order->status);
        $this->assertSame('pending', $order->payment_status);
        $this->assertTrue($order->payment_expires_at->isFuture());

        $requests = $order->serviceRequests()->get();
        $this->assertCount(2, $requests);
        foreach ($requests as $request) {
            $this->assertSame('pending', $request->status);
            $this->assertSame($this->partner->user_id, $request->partner_id);
            $this->assertNull($request->assignment_expires_at);
        }

        // Settle the retried session through the real service path; the
        // preferred partner's requests must be promoted to 'assigned'.
        $payment = Payment::where('booking_id', $order->order_id)->latest('id')->first();
        $this->assertNotNull($payment);
        $this->assertSame('initiated', $payment->status);

        Http::fake([
            '*api/epay/transaction/status*' => Http::response([
                'status' => 'COMPLETE',
                'ref_id' => 'REF-1',
                'total_amount' => number_format((float) $order->total_amount, 2, '.', ''),
            ], 200),
        ]);

        $result = app(PaymentService::class)->verifyAndUpdatePayment('esewa', [
            'transaction_uuid' => $payment->txn_id,
            'total_amount' => $order->total_amount,
        ]);

        $this->assertTrue($result['success']);
        $this->assertSame('paid', $order->fresh()->payment_status);
        $this->assertSame('partner_assigned', $order->fresh()->status);
        $this->assertSame('paid', $payment->fresh()->status);

        foreach ($order->serviceRequests()->get() as $request) {
            $this->assertSame('assigned', $request->status);
            $this->assertNotNull($request->assignment_expires_at);
        }
    }

    public function test_failure_endpoint_never_cancels_a_paid_order(): void
    {
        $order = $this->createOnlineOrder();
        $order->update(['payment_status' => 'paid', 'status' => 'confirmed']);

        $this->postJson("/api/v1/bookings/{$order->order_id}/payment-failed")
            ->assertStatus(200)
            ->assertJsonPath('already_paid', true);

        $this->assertSame('confirmed', $order->fresh()->status);
        $this->assertSame('paid', $order->fresh()->payment_status);
        $this->assertCount(2, $order->serviceRequests()->where('status', 'pending')->get());
    }
}
