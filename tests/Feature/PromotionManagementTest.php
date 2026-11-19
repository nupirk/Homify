<?php

namespace Tests\Feature;

use App\Models\Promotion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\CreatesApplication;
use Tests\TestCase;

/**
 * Promotion CMS contract:
 *  - create/update enforce exactly one discount channel and derive promo_type
 *  - codes are stored uppercase and stay unique case-insensitively
 *  - the public active feed hides inactive/expired/exhausted/future codes
 *  - validatePromo semantics are untouched (covered by PromoEngineTest)
 */
class PromotionManagementTest extends TestCase
{
    use CreatesApplication, RefreshDatabase;

    private User $admin;
    private User $customer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::create([
            'first_name' => 'Promo',
            'last_name' => 'Admin',
            'email' => fake()->unique()->safeEmail(),
            'phone' => '98' . rand(10000000, 99999999),
            'password' => bcrypt('password'),
            'user_type' => 'admin',
            'is_active' => true,
        ]);

        $this->customer = User::create([
            'first_name' => 'Active',
            'last_name' => 'Customer',
            'email' => fake()->unique()->safeEmail(),
            'phone' => '98' . rand(10000000, 99999999),
            'password' => bcrypt('password'),
            'user_type' => 'customer',
            'is_active' => true,
        ]);
    }

    private function makePromo(array $overrides = []): Promotion
    {
        return Promotion::create(array_merge([
            'promo_code' => 'SEED10',
            'title' => 'Seeded promo',
            'promo_type' => 'percentage',
            'discount_percent' => 10,
            'valid_from' => now()->subDay(),
            'valid_until' => now()->addDays(7),
            'is_active' => true,
        ], $overrides));
    }

    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'promo_code' => 'WINTER20',
            'title' => 'Winter Sale',
            'description' => 'Seasonal discount',
            'discount_percent' => 20,
            'valid_from' => now()->subDay()->format('Y-m-d H:i:s'),
            'valid_until' => now()->addDays(7)->format('Y-m-d H:i:s'),
            'is_active' => true,
        ], $overrides);
    }

    public function test_active_feed_hides_codes_the_customer_already_redeemed(): void
    {
        $used = $this->makePromo(['promo_code' => 'USED10']);
        $this->makePromo(['promo_code' => 'FRESH10', 'title' => 'Fresh promo']);

        DB::table('user_promotions')->insert([
            'user_id' => $this->customer->user_id,
            'promotion_id' => $used->promotion_id,
            'used' => true,
            'used_at' => now(),
            'created_at' => now(),
        ]);

        // Guests still get the whole live catalogue, redeemed or not.
        $guestCodes = collect(
            $this->getJson('/api/v1/promotions/active')->assertOk()->json('data.promotions')
        )->pluck('promo_code');
        $this->assertTrue($guestCodes->contains('USED10'));

        // A signed-in customer is not advertised a code they cannot apply.
        Sanctum::actingAs($this->customer, ['*']);
        $codes = collect(
            $this->getJson('/api/v1/promotions/active')->assertOk()->json('data.promotions')
        )->pluck('promo_code');

        $this->assertFalse($codes->contains('USED10'));
        $this->assertTrue($codes->contains('FRESH10'));
    }

    public function test_admin_create_requires_exactly_one_discount_channel(): void
    {
        Sanctum::actingAs($this->admin, ['*']);

        $this->postJson('/api/v1/admin/promotions', $this->validPayload(['discount_percent' => null]))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['discount_percent', 'discount_amount']);

        $this->postJson('/api/v1/admin/promotions', $this->validPayload(['discount_amount' => 100]))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['discount_percent', 'discount_amount']);

        $this->assertDatabaseCount('promotions', 0);
    }

    public function test_admin_create_percentage_promo_uppercases_code_and_sets_promo_type(): void
    {
        Sanctum::actingAs($this->admin, ['*']);
        $res = $this->postJson('/api/v1/admin/promotions', $this->validPayload([
            'promo_code' => 'winter20', // stored uppercase
        ]));

        $res->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.promotion.promo_code', 'WINTER20')
            ->assertJsonPath('data.promotion.promo_type', 'percentage');
        $this->assertEquals(20.0, $res->json('data.promotion.discount_percent'));

        $this->assertDatabaseHas('promotions', [
            'promo_code' => 'WINTER20',
            'promo_type' => 'percentage',
        ]);
    }

    public function test_admin_create_fixed_amount_promo_sets_fixed_amount_type(): void
    {
        Sanctum::actingAs($this->admin, ['*']);
        $res = $this->postJson('/api/v1/admin/promotions', $this->validPayload([
            'discount_percent' => null,
            'discount_amount' => 500,
            'max_discount' => 250,
        ]));

        $res->assertCreated()->assertJsonPath('data.promotion.promo_type', 'fixed_amount');
        $this->assertNull($res->json('data.promotion.discount_percent'));
        $this->assertEquals(500.0, $res->json('data.promotion.discount_amount'));

        $this->assertDatabaseHas('promotions', ['promo_type' => 'fixed_amount']);
    }

    public function test_admin_create_rejects_duplicate_codes_and_out_of_range_discounts(): void
    {
        $this->makePromo(['promo_code' => 'TAKEN10']);

        Sanctum::actingAs($this->admin, ['*']);

        // case-insensitive duplicate: the code is uppercased before the unique check
        $this->postJson('/api/v1/admin/promotions', $this->validPayload(['promo_code' => 'taken10']))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['promo_code']);

        $this->postJson('/api/v1/admin/promotions', $this->validPayload(['discount_percent' => 150]))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['discount_percent']);

        $this->postJson('/api/v1/admin/promotions', $this->validPayload([
            'valid_from' => now()->addDays(2)->format('Y-m-d H:i:s'),
            'valid_until' => now()->format('Y-m-d H:i:s'),
        ]))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['valid_until']);

        $this->assertSame(1, Promotion::count());
    }

    public function test_admin_promotions_list_includes_usage_count(): void
    {
        $this->makePromo(['promo_code' => 'USED7', 'usage_count' => 7, 'usage_limit' => 10]);
        $this->makePromo(['promo_code' => 'OPEN2', 'usage_count' => 2]);

        Sanctum::actingAs($this->admin, ['*']);
        $res = $this->getJson('/api/v1/admin/promotions');

        $res->assertOk()->assertJsonCount(2, 'data.promotions');
        $byCode = collect($res->json('data.promotions'))->keyBy('promo_code');
        $this->assertSame(7, $byCode['USED7']['usage_count']);
        $this->assertSame(10, $byCode['USED7']['usage_limit']);
        $this->assertNull($byCode['OPEN2']['usage_limit']);
    }

    public function test_active_promotions_exclude_inactive_expired_exhausted_and_future(): void
    {
        $live = $this->makePromo(['promo_code' => 'LIVE10']);
        $partial = $this->makePromo(['promo_code' => 'PART4', 'usage_count' => 4, 'usage_limit' => 5]);
        $this->makePromo(['promo_code' => 'OFF', 'is_active' => false]);
        $this->makePromo(['promo_code' => 'OLD', 'valid_until' => now()->subDay()]);
        $this->makePromo(['promo_code' => 'SOON', 'valid_from' => now()->addDay()]);
        $this->makePromo(['promo_code' => 'SPENT', 'usage_count' => 2, 'usage_limit' => 2]);

        Sanctum::actingAs($this->customer, ['*']);
        $res = $this->getJson('/api/v1/promotions/active');

        $res->assertOk()->assertJsonCount(2, 'data.promotions');
        $this->assertSame(
            [$live->promotion_id, $partial->promotion_id],
            array_column($res->json('data.promotions'), 'promotion_id')
        );
        $res->assertJsonStructure([
            'success',
            'data' => ['promotions' => [[
                'promotion_id',
                'promo_code',
                'title',
                'description',
                'discount_percent',
                'discount_amount',
                'max_discount',
                'min_order_value',
                'promo_type',
                'valid_until',
            ]]],
        ]);
    }

    public function test_active_promotions_are_readable_by_guests(): void
    {
        // Checkout shows available offers to guests too; the code is only ever
        // applied by an authenticated order, and validate still requires auth.
        app('auth')->forgetGuards();

        $live = $this->makePromo(['promo_code' => 'GUEST10']);

        $res = $this->getJson('/api/v1/promotions/active');

        $res->assertOk()->assertJsonPath('success', true);
        $this->assertContains(
            $live->promo_code,
            array_column($res->json('data.promotions'), 'promo_code')
        );
    }

    public function test_admin_promotion_update_applies_the_same_discount_rules(): void
    {
        $promotion = $this->makePromo(['promo_code' => 'SWITCH', 'discount_percent' => 10]);

        Sanctum::actingAs($this->admin, ['*']);
        $base = $this->validPayload(['promo_code' => 'SWITCH']);

        $this->putJson("/api/v1/admin/promotions/{$promotion->promotion_id}", array_merge($base, [
            'discount_percent' => 10,
            'discount_amount' => 100,
        ]))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['discount_percent', 'discount_amount']);

        $this->putJson("/api/v1/admin/promotions/{$promotion->promotion_id}", array_merge($base, [
            'discount_percent' => null,
            'discount_amount' => 300,
        ]))
            ->assertOk()
            ->assertJsonPath('data.promotion.promo_type', 'fixed_amount');

        $promotion->refresh();
        $this->assertSame('SWITCH', $promotion->promo_code);
        $this->assertNull($promotion->discount_percent);
        $this->assertEquals(300, (float) $promotion->discount_amount);
    }

    public function test_admin_can_scope_a_promo_to_one_service(): void
    {
        $category = \App\Models\Category::create(['name' => 'Cleaning', 'is_active' => true]);
        $service = \App\Models\Service::create([
            'category_id' => $category->category_id,
            'name' => 'Deep Cleaning',
            'description' => 'Deep clean',
            'is_active' => true,
        ]);

        Sanctum::actingAs($this->admin, ['*']);

        $res = $this->postJson('/api/v1/admin/promotions', $this->validPayload([
            'service_id' => $service->service_id,
        ]));

        $res->assertCreated()
            ->assertJsonPath('data.promotion.service_id', $service->service_id)
            ->assertJsonPath('data.promotion.service_name', 'Deep Cleaning');
        $this->assertDatabaseHas('promotions', [
            'promo_code' => 'WINTER20',
            'service_id' => $service->service_id,
        ]);

        // Unknown services are refused.
        $this->postJson('/api/v1/admin/promotions', $this->validPayload([
            'promo_code' => 'BADID',
            'service_id' => 99999,
        ]))->assertStatus(422)->assertJsonValidationErrors(['service_id']);

        // Zero is not a discount.
        $this->postJson('/api/v1/admin/promotions', $this->validPayload([
            'promo_code' => 'ZERO',
            'discount_percent' => 0,
        ]))->assertStatus(422)->assertJsonValidationErrors(['discount_percent']);
    }

    public function test_admin_promotion_routes_reject_customers_and_guests(): void
    {
        Sanctum::actingAs($this->customer, ['*']);
        $this->getJson('/api/v1/admin/promotions')->assertStatus(403);
        $this->postJson('/api/v1/admin/promotions', $this->validPayload())->assertStatus(403);

        app('auth')->forgetGuards();
        $this->getJson('/api/v1/admin/promotions')->assertStatus(401);
    }
}
