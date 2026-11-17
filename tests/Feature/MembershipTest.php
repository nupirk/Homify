<?php

namespace Tests\Feature;

use App\Models\Address;
use App\Models\Category;
use App\Models\MembershipPlan;
use App\Models\Order;
use App\Models\Promotion;
use App\Models\Service;
use App\Models\ServicePricing;
use App\Models\User;
use App\Models\UserMembership;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\CreatesApplication;
use Tests\TestCase;

/**
 * Urban Plus membership contract:
 *  - plans/status endpoints and the signed eSewa purchase flow
 *  - signed webhook activates the entitlement window exactly once
 *  - checkout applies member percent on the POST-promo base, capped by the
 *    plan perk, inside discount_amount (tax math unchanged)
 *  - expired members get nothing; amount mismatches never activate
 */
class MembershipTest extends TestCase
{
    use CreatesApplication, RefreshDatabase;

    private User $customer;
    private Address $address;
    private Service $service;

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
            'first_name' => 'Member',
            'last_name' => 'Customer',
            'email' => fake()->unique()->safeEmail(),
            'phone' => '98' . rand(10000000, 99999999),
            'password' => bcrypt('password'),
            'user_type' => 'customer',
            'is_active' => true,
        ]);

        $this->address = Address::create([
            'user_id' => $this->customer->user_id,
            'address_line_1' => 'Member Street',
            'city' => 'Kathmandu',
            'state' => 'Bagmati',
            'postal_code' => '44600',
            'country' => 'Nepal',
            'latitude' => 27.7172,
            'longitude' => 85.3240,
            'is_default' => true,
        ]);

        $category = Category::create(['name' => 'Cleaning', 'is_active' => true]);
        $this->service = Service::create([
            'category_id' => $category->category_id,
            'name' => 'Home Cleaning',
            'description' => 'Standard home cleaning',
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
    }

    private function plan(): MembershipPlan
    {
        return MembershipPlan::where('code', 'urban_plus')->firstOrFail();
    }

    private function activateMembership(): UserMembership
    {
        $plan = $this->plan();

        return UserMembership::create([
            'user_id' => $this->customer->user_id,
            'plan_id' => $plan->id,
            'status' => 'active',
            'price_paid' => $plan->price,
            'gateway' => 'esewa',
            'txn_id' => 'MBR-' . Str::uuid()->toString(),
            'starts_at' => now(),
            'ends_at' => now()->addDays(30),
        ]);
    }

    private function bookingPayload(array $extra = []): array
    {
        return array_merge([
            'services' => [['service_id' => $this->service->service_id, 'quantity' => 1]],
            'scheduled_at' => now()->addDay()->setTime(10, 0)->format('Y-m-d H:i'),
            'address_id' => $this->address->address_id,
            'payment_method' => 'pas',
        ], $extra);
    }

    /**
     * Signed eSewa success URL, mirroring the gateway's envelope format.
     */
    private function successUrl(string $txn, string $totalAmount): string
    {
        $data = [
            'transaction_code' => '111111',
            'status' => 'COMPLETE',
            'total_amount' => $totalAmount,
            'transaction_uuid' => $txn,
            'product_code' => (string) config('payment.gateways.esewa.product_code'),
            'signed_field_names' => 'transaction_code,status,total_amount,transaction_uuid,product_code,signed_field_names',
        ];

        $message = "transaction_code={$data['transaction_code']},status={$data['status']},total_amount={$data['total_amount']},transaction_uuid={$data['transaction_uuid']},product_code={$data['product_code']},signed_field_names={$data['signed_field_names']}";
        $data['signature'] = base64_encode(
            hash_hmac('sha256', $message, (string) config('payment.gateways.esewa.secret_key'), true)
        );

        return '/api/v1/webhooks/membership/esewa/success?data='
            . urlencode(base64_encode(json_encode($data)));
    }

    public function test_membership_endpoints_require_auth(): void
    {
        $this->getJson('/api/v1/membership')->assertStatus(401);
        $this->postJson('/api/v1/membership/purchase', ['plan_id' => 1])
            ->assertStatus(401);
    }

    public function test_index_returns_urban_plus_plan_and_null_membership(): void
    {
        Sanctum::actingAs($this->customer, ['*']);

        $res = $this->getJson('/api/v1/membership')->assertStatus(200);

        $plan = collect($res->json('plans'))->firstWhere('code', 'urban_plus');
        $this->assertNotNull($plan, 'Seeded urban_plus plan must be listed');
        $this->assertSame(299.0, (float) $plan['price']);
        $this->assertSame(30, $plan['duration_days']);
        $this->assertSame(10.0, (float) $plan['perks']['discount_percent']);
        $this->assertSame(500.0, (float) $plan['perks']['max_discount']);
        $this->assertNull($res->json('membership'));
    }

    public function test_purchase_creates_pending_membership_with_signed_esewa_payload(): void
    {
        Sanctum::actingAs($this->customer, ['*']);

        $res = $this->postJson('/api/v1/membership/purchase', [
            'plan_id' => $this->plan()->id,
        ])->assertStatus(200);

        $this->assertStringContainsString('/api/epay/main/v2/form', $res->json('payment_url'));

        $payload = $res->json('payload');
        $this->assertSame('299.00', $payload['total_amount']);
        $this->assertSame('299.00', $payload['amount']);
        $this->assertSame('total_amount,transaction_uuid,product_code', $payload['signed_field_names']);
        $this->assertStringContainsString('/webhooks/membership/esewa/success', $payload['success_url']);
        $this->assertStringContainsString('/webhooks/membership/esewa/failure', $payload['failure_url']);

        // Signature over the EXACT posted amount string.
        $expectedSig = base64_encode(hash_hmac(
            'sha256',
            "total_amount=299.00,transaction_uuid={$payload['transaction_uuid']},product_code={$payload['product_code']}",
            (string) config('payment.gateways.esewa.secret_key'),
            true
        ));
        $this->assertSame($expectedSig, $payload['signature']);

        $membership = UserMembership::where('user_id', $this->customer->user_id)->firstOrFail();
        $this->assertSame('pending', $membership->status);
        $this->assertSame($payload['transaction_uuid'], $membership->txn_id);
        $this->assertSame('esewa', $membership->gateway);
        $this->assertNotNull($membership->payment_expires_at);
    }

    public function test_webhook_activates_membership_and_is_idempotent(): void
    {
        Sanctum::actingAs($this->customer, ['*']);
        $this->postJson('/api/v1/membership/purchase', [
            'plan_id' => $this->plan()->id,
        ])->assertStatus(200);
        app('auth')->forgetGuards();

        $membership = UserMembership::where('user_id', $this->customer->user_id)->firstOrFail();

        Http::fake([
            '*api/epay/transaction/status*' => Http::response([
                'status' => 'COMPLETE',
                'ref_id' => 'REF-MBR-1',
                'total_amount' => '299.00',
            ], 200),
        ]);

        $this->get($this->successUrl($membership->txn_id, '299.00'))
            ->assertStatus(302)
            ->assertRedirect('fe://profile/membership?payment_status=success');

        $membership->refresh();
        $this->assertSame('active', $membership->status);
        $this->assertNotNull($membership->starts_at);
        $this->assertEqualsWithDelta(
            now()->addDays(30)->getTimestamp(),
            $membership->ends_at->getTimestamp(),
            60
        );

        // Replay: same callback twice must not move the window.
        $originalEndsAt = $membership->ends_at->getTimestamp();

        $this->get($this->successUrl($membership->txn_id, '299.00'))
            ->assertStatus(302)
            ->assertRedirect('fe://profile/membership?payment_status=success');

        $membership->refresh();
        $this->assertSame('active', $membership->status);
        $this->assertSame($originalEndsAt, $membership->ends_at->getTimestamp());
        $this->assertSame(
            1,
            UserMembership::where('txn_id', $membership->txn_id)->count()
        );
    }

    public function test_active_membership_discounts_booking_checkout(): void
    {
        $this->activateMembership();

        Sanctum::actingAs($this->customer, ['*']);
        $res = $this->postJson('/api/v1/bookings', $this->bookingPayload())
            ->assertStatus(201);

        $order = Order::findOrFail($res->json('order_id'));

        // 1500 gross - 150 member (10%) = 1350 taxable, tax 175.50.
        $this->assertSame(1500.0, (float) $order->subtotal);
        $this->assertSame(150.0, (float) $order->membership_discount);
        $this->assertSame(150.0, (float) $order->discount_amount);
        $this->assertSame(175.5, (float) $order->tax_amount);
        $this->assertSame(1525.5, (float) $order->total_amount);
        $this->assertSame(1525.5, (float) $order->final_payable);
        $this->assertNull($order->promo_code);

        // Status endpoint reflects the active window.
        $status = $this->getJson('/api/v1/membership')->assertStatus(200);
        $this->assertSame('active', $status->json('membership.status'));
        $this->assertSame(30, $status->json('membership.days_left'));
        $this->assertSame(10.0, (float) $status->json('membership.discount_percent'));
    }

    public function test_promo_and_membership_stack(): void
    {
        Promotion::create([
            'promo_code' => 'TEST10',
            'title' => 'Test 10% off',
            'promo_type' => 'percentage',
            'discount_percent' => 10,
            'valid_from' => now()->subDay(),
            'valid_until' => now()->addDays(7),
            'is_active' => true,
        ]);
        $this->activateMembership();

        Sanctum::actingAs($this->customer, ['*']);
        $res = $this->postJson('/api/v1/bookings', $this->bookingPayload([
            'promo_code' => 'TEST10',
        ]))->assertStatus(201);

        $order = Order::findOrFail($res->json('order_id'));

        // Promo: 1500 -> 1350. Member 10% of 1350 -> 135. Base 1215,
        // tax 157.95, total 1372.95.
        $this->assertSame(1500.0, (float) $order->subtotal);
        $this->assertSame(135.0, (float) $order->membership_discount);
        $this->assertSame(285.0, (float) $order->discount_amount);
        $this->assertSame('TEST10', trim((string) $order->promo_code));
        $this->assertSame(157.95, (float) $order->tax_amount);
        $this->assertSame(1372.95, (float) $order->total_amount);
    }

    public function test_expired_membership_gets_no_discount(): void
    {
        $membership = $this->activateMembership();
        $membership->update(['ends_at' => now()->subDay()]);

        Sanctum::actingAs($this->customer, ['*']);
        $res = $this->postJson('/api/v1/bookings', $this->bookingPayload())
            ->assertStatus(201);

        $order = Order::findOrFail($res->json('order_id'));

        $this->assertSame(0.0, (float) $order->membership_discount);
        $this->assertSame(0.0, (float) $order->discount_amount);
        $this->assertSame(195.0, (float) $order->tax_amount);
        $this->assertSame(1695.0, (float) $order->total_amount);

        // Lazy expiry: the stale row must be marked expired, not active.
        $this->assertSame('expired', $membership->refresh()->status);
    }

    public function test_amount_mismatch_does_not_activate(): void
    {
        Sanctum::actingAs($this->customer, ['*']);
        $this->postJson('/api/v1/membership/purchase', [
            'plan_id' => $this->plan()->id,
        ])->assertStatus(200);
        app('auth')->forgetGuards();

        $membership = UserMembership::where('user_id', $this->customer->user_id)->firstOrFail();

        Http::fake([
            '*api/epay/transaction/status*' => Http::response([
                'status' => 'COMPLETE',
                'ref_id' => 'REF-MBR-2',
                'total_amount' => '1.00',
            ], 200),
        ]);

        $res = $this->get($this->successUrl($membership->txn_id, '299.00'));
        $res->assertStatus(302);
        $this->assertStringContainsString(
            'payment_status=failed',
            (string) $res->headers->get('Location')
        );

        $this->assertSame('pending', $membership->refresh()->status);
    }
}
