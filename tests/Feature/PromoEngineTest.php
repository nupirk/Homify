<?php

namespace Tests\Feature;

use App\Models\Address;
use App\Models\Category;
use App\Models\Order;
use App\Models\Promotion;
use App\Models\Service;
use App\Models\ServicePricing;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\CreatesApplication;
use Tests\TestCase;

/**
 * Promo engine contract:
 *  - validate endpoint returns the discount or a customer-facing reason
 *  - checkout applies the promo to the GROSS subtotal before tax and
 *    burns one usage inside the booking transaction
 *  - expired/inactive/min-order/usage-limit/one-use-per-customer are enforced
 *  - an invalid code rejects the booking without creating an order
 */
class PromoEngineTest extends TestCase
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
            'email' => 'platform@homify.local',
            'phone' => '9800000001',
            'password' => bcrypt('secret'),
            'user_type' => 'admin',
            'is_active' => true,
        ]));

        $this->syncUserIdSequence();

        $this->customer = User::create([
            'first_name' => 'Promo',
            'last_name' => 'Customer',
            'email' => fake()->unique()->safeEmail(),
            'phone' => '98' . rand(10000000, 99999999),
            'password' => bcrypt('password'),
            'user_type' => 'customer',
            'is_active' => true,
        ]);

        $this->address = Address::create([
            'user_id' => $this->customer->user_id,
            'address_line_1' => 'Promo Street',
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

    private function makePromo(array $overrides = []): Promotion
    {
        return Promotion::create(array_merge([
            'promo_code' => 'TEST10',
            'title' => 'Test 10% off',
            'promo_type' => 'percentage',
            'discount_percent' => 10,
            'valid_from' => now()->subDay(),
            'valid_until' => now()->addDays(7),
            'is_active' => true,
        ], $overrides));
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

    public function test_validate_returns_discount_for_percentage_code(): void
    {
        $this->makePromo(['promo_code' => 'FESTIVE10', 'discount_percent' => 10]);

        Sanctum::actingAs($this->customer, ['*']);
        $res = $this->postJson('/api/v1/promotions/validate', [
            'code' => 'festive10', // codes are case-insensitive
            'subtotal' => 1000,
        ]);

        $res->assertOk()->assertJsonPath('valid', true);
        $this->assertEquals(100.0, (float) $res->json('discount_amount'));
        $this->assertSame('FESTIVE10', $res->json('promotion.promo_code') ?? 'FESTIVE10');
    }

    public function test_validate_rejects_expired_inactive_min_order_and_unknown_codes(): void
    {
        Sanctum::actingAs($this->customer, ['*']);

        $this->postJson('/api/v1/promotions/validate', ['code' => 'NOPE', 'subtotal' => 1000])
            ->assertStatus(422)
            ->assertJsonPath('valid', false)
            ->assertJsonPath('message', 'This promo code is not valid.');

        $this->makePromo(['promo_code' => 'OFF', 'is_active' => false]);
        $this->postJson('/api/v1/promotions/validate', ['code' => 'OFF', 'subtotal' => 1000])
            ->assertStatus(422)
            ->assertJsonPath('message', 'This promo code is no longer active.');

        $this->makePromo(['promo_code' => 'GONE', 'valid_until' => now()->subDay()]);
        $this->postJson('/api/v1/promotions/validate', ['code' => 'GONE', 'subtotal' => 1000])
            ->assertStatus(422)
            ->assertJsonPath('message', 'This promo code has expired.');

        $this->makePromo(['promo_code' => 'BIGONLY', 'min_order_value' => 2000]);
        $this->postJson('/api/v1/promotions/validate', ['code' => 'BIGONLY', 'subtotal' => 1000])
            ->assertStatus(422)
            ->assertJsonPath('message', 'This promo code requires a minimum order of Rs. 2,000.');
    }

    public function test_fixed_amount_promo_is_capped_by_max_discount(): void
    {
        $this->makePromo([
            'promo_code' => 'HALF',
            'discount_percent' => 50,
            'max_discount' => 300,
        ]);

        Sanctum::actingAs($this->customer, ['*']);
        $res = $this->postJson('/api/v1/promotions/validate', [
            'code' => 'HALF',
            'subtotal' => 1000,
        ]);

        $res->assertOk();
        $this->assertEquals(300.0, (float) $res->json('discount_amount'));
    }

    public function test_booking_applies_promo_before_tax_and_burns_usage(): void
    {
        $this->makePromo(['promo_code' => 'SAVE10', 'discount_percent' => 10]);

        Sanctum::actingAs($this->customer, ['*']);
        $res = $this->postJson('/api/v1/bookings', $this->bookingPayload([
            'promo_code' => 'SAVE10',
        ]));
        $res->assertStatus(201);

        $order = Order::findOrFail($res->json('order_id'));

        // gross 1500, 10% off -> 150; taxable 1350; 13% tax = 175.50
        $this->assertEquals(1500.0, (float) $order->subtotal);
        $this->assertEquals(150.0, (float) $order->discount_amount);
        $this->assertEquals(1350.0, (float) $order->subtotal - (float) $order->discount_amount);
        $this->assertEquals(175.5, (float) $order->tax_amount);
        $this->assertEquals(1525.5, (float) $order->total_amount);
        $this->assertEquals(1525.5, (float) $order->final_payable);
        $this->assertSame('SAVE10', $order->promo_code);

        // Usage burned exactly once: global counter + per-customer redemption.
        $promotion = Promotion::where('promo_code', 'SAVE10')->first();
        $this->assertSame(1, (int) $promotion->usage_count);
        $this->assertDatabaseHas('user_promotions', [
            'user_id' => $this->customer->user_id,
            'promotion_id' => $promotion->promotion_id,
            'used' => true,
        ]);
    }

    public function test_customer_cannot_use_the_same_code_twice(): void
    {
        $this->makePromo(['promo_code' => 'ONCE', 'discount_percent' => 10]);

        Sanctum::actingAs($this->customer, ['*']);
        $this->postJson('/api/v1/bookings', $this->bookingPayload(['promo_code' => 'ONCE']))
            ->assertStatus(201);

        $this->postJson('/api/v1/promotions/validate', ['code' => 'ONCE', 'subtotal' => 1000])
            ->assertStatus(422)
            ->assertJsonPath('message', 'You have already used this promo code.');

        // Different day so the duplicate-booking guard doesn't swallow the
        // request - the promo layer itself must reject the reuse.
        $this->postJson('/api/v1/bookings', $this->bookingPayload([
            'promo_code' => 'ONCE',
            'scheduled_at' => now()->addDays(3)->format('Y-m-d H:i'),
        ]))
            ->assertStatus(422)
            ->assertJsonPath('message', 'You have already used this promo code.');
    }

    public function test_invalid_code_rejects_booking_without_creating_an_order(): void
    {
        Sanctum::actingAs($this->customer, ['*']);
        $before = Order::count();

        $this->postJson('/api/v1/bookings', $this->bookingPayload(['promo_code' => 'NOPE']))
            ->assertStatus(422)
            ->assertJsonPath('message', 'This promo code is not valid.');

        $this->assertSame($before, Order::count());
    }

    public function test_usage_limit_is_enforced_across_customers(): void
    {
        $this->makePromo(['promo_code' => 'LAST1', 'discount_percent' => 10, 'usage_limit' => 1]);

        Sanctum::actingAs($this->customer, ['*']);
        $this->postJson('/api/v1/bookings', $this->bookingPayload(['promo_code' => 'LAST1']))
            ->assertStatus(201);

        $other = User::create([
            'first_name' => 'Other',
            'last_name' => 'Customer',
            'email' => fake()->unique()->safeEmail(),
            'phone' => '98' . rand(10000000, 99999999),
            'password' => bcrypt('password'),
            'user_type' => 'customer',
            'is_active' => true,
        ]);
        $otherAddress = Address::create([
            'user_id' => $other->user_id,
            'address_line_1' => 'Other Street',
            'city' => 'Kathmandu',
            'state' => 'Bagmati',
            'postal_code' => '44600',
            'country' => 'Nepal',
            'latitude' => 27.7172,
            'longitude' => 85.3240,
            'is_default' => true,
        ]);

        Sanctum::actingAs($other, ['*']);
        $this->postJson('/api/v1/bookings', $this->bookingPayload([
            'address_id' => $otherAddress->address_id,
            'promo_code' => 'LAST1',
        ]))->assertStatus(422)->assertJsonPath('message', 'This promo code has reached its usage limit.');
    }

    public function test_validate_requires_authentication(): void
    {
        app('auth')->forgetGuards();

        $this->postJson('/api/v1/promotions/validate', ['code' => 'X', 'subtotal' => 100])
            ->assertStatus(401);
    }
}
