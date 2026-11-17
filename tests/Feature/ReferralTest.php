<?php

namespace Tests\Feature;

use App\Models\Address;
use App\Models\Category;
use App\Models\Notification;
use App\Models\Order;
use App\Models\PartnerProfile;
use App\Models\ReferralReward;
use App\Models\Service;
use App\Models\ServiceCompletionDocument;
use App\Models\ServicePricing;
use App\Models\User;
use App\Services\ReferralService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\CreatesApplication;
use Tests\TestCase;

/**
 * Customer referral contract:
 *  - every customer gets a persistent invite code (GET /referral)
 *  - redeeming attributes exactly once, blocks self/unknown/re-redeem,
 *    and issues the referee a welcome promo immediately
 *  - the referrer is rewarded ONLY when the referee completes a booking
 *    (reward promo + in-app notification, no cash touches the ledger)
 */
class ReferralTest extends TestCase
{
    use CreatesApplication, RefreshDatabase;

    private User $referrer;
    private User $referee;
    private User $partner;
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

        $this->referrer = User::create([
            'first_name' => 'Re',
            'last_name' => 'Ferrer',
            'email' => fake()->unique()->safeEmail(),
            'phone' => '98' . rand(10000000, 99999999),
            'password' => bcrypt('password'),
            'user_type' => 'customer',
            'is_active' => true,
        ]);

        $this->referee = User::create([
            'first_name' => 'Re',
            'last_name' => 'Feree',
            'email' => fake()->unique()->safeEmail(),
            'phone' => '98' . rand(10000000, 99999999),
            'password' => bcrypt('password'),
            'user_type' => 'customer',
            'is_active' => true,
        ]);

        $this->partner = User::create([
            'first_name' => 'Job',
            'last_name' => 'Partner',
            'email' => fake()->unique()->safeEmail(),
            'phone' => '98' . rand(10000000, 99999999),
            'password' => bcrypt('password'),
            'user_type' => 'partner',
            'is_active' => true,
        ]);

        PartnerProfile::unguarded(fn () => PartnerProfile::create([
            'partner_id' => $this->partner->user_id,
            'status' => 'active',
        ]));

        $this->address = Address::create([
            'user_id' => $this->referee->user_id,
            'address_line_1' => 'Invite Street',
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
        $this->partner->partnerProfile->services()->attach($this->service->service_id);
    }

    private function redeemAsReferee(): string
    {
        $code = ReferralService::codeFor($this->referrer);

        Sanctum::actingAs($this->referee, ['*']);
        $this->postJson('/api/v1/referral/apply', ['code' => $code])
            ->assertOk()
            ->assertJsonPath('success', true);

        return $code;
    }

    private function refereeBooksAndCompletes(): Order
    {
        Sanctum::actingAs($this->referee, ['*']);
        $res = $this->postJson('/api/v1/bookings', [
            'services' => [['service_id' => $this->service->service_id, 'quantity' => 1]],
            'scheduled_at' => now()->addDay()->setTime(10, 0)->format('Y-m-d H:i'),
            'address_id' => $this->address->address_id,
            'payment_method' => 'pas',
            'preferred_partner_id' => $this->partner->user_id,
        ]);
        $res->assertStatus(201);
        $order = Order::findOrFail($res->json('order_id'));

        Sanctum::actingAs($this->partner, ['*']);
        $this->patchJson("/api/v1/bookings/{$order->order_id}/accept")->assertStatus(200);
        $this->patchJson("/api/v1/bookings/{$order->order_id}/start-work")->assertStatus(200);

        $request = $order->serviceRequests()->first();
        ServiceCompletionDocument::unguarded(fn () => ServiceCompletionDocument::create([
            'service_request_id' => $request->request_id,
            'partner_id' => $this->partner->user_id,
            'uploaded_by' => $this->partner->user_id,
            'document_type' => 'after_photo',
            'original_filename' => 'after.jpg',
            'stored_filename' => 'after.jpg',
            'file_path' => 'completions/test/after.jpg',
            'mime_type' => 'image/jpeg',
            'file_size' => 1024,
            'file_hash' => 'abc123',
            'access_token' => Str::uuid()->toString(),
        ]));

        $this->patchJson("/api/v1/bookings/{$order->order_id}/complete", [
            'payment_received' => 'cash',
        ])->assertStatus(200);

        return $order;
    }

    public function test_referral_endpoint_generates_a_persistent_invite_code(): void
    {
        Sanctum::actingAs($this->referrer, ['*']);

        $first = $this->getJson('/api/v1/referral');
        $first->assertOk()->assertJsonPath('success', true);
        $code = $first->json('code');

        $this->assertSame(8, strlen((string) $code));
        $this->assertFalse((bool) $first->json('has_applied_code'));
        $this->assertSame(0, (int) $first->json('referred_count'));

        $second = $this->getJson('/api/v1/referral');
        $this->assertSame($code, $second->json('code'), 'Invite code must be stable across calls');
    }

    public function test_redeeming_attributes_referrer_and_issues_welcome_promo(): void
    {
        $code = $this->redeemAsReferee();

        $this->referee->refresh();
        $this->assertSame((int) $this->referrer->user_id, (int) $this->referee->referred_by);

        $reward = ReferralReward::where('referee_id', $this->referee->user_id)->first();
        $this->assertNotNull($reward);
        $this->assertSame((int) $this->referrer->user_id, (int) $reward->referrer_id);
        $this->assertSame('pending', $reward->status);
        $this->assertNotNull($reward->welcome_promo_code);

        // The welcome promo is real, assigned and usable on the next booking.
        Sanctum::actingAs($this->referee, ['*']);
        $res = $this->postJson('/api/v1/promotions/validate', [
            'code' => $reward->welcome_promo_code,
            'subtotal' => 1000,
        ]);
        $res->assertOk()->assertJsonPath('valid', true);
        $this->assertEquals(150.0, (float) $res->json('discount_amount'), 'Welcome promo is 15% off');

        // Referee got the in-app notification and the info screen reflects it.
        $this->assertTrue(
            Notification::where('user_id', $this->referee->user_id)
                ->where('title', 'Welcome promo unlocked!')
                ->exists()
        );
        $this->getJson('/api/v1/referral')
            ->assertOk()
            ->assertJsonPath('has_applied_code', true);
    }

    public function test_redeem_rejects_unknown_self_and_second_use(): void
    {
        Sanctum::actingAs($this->referee, ['*']);
        $this->postJson('/api/v1/referral/apply', ['code' => 'ZZZZZZZZ'])
            ->assertStatus(422)
            ->assertJsonPath('message', 'This referral code is not valid.');

        $ownCode = ReferralService::codeFor($this->referee);
        $this->postJson('/api/v1/referral/apply', ['code' => $ownCode])
            ->assertStatus(422)
            ->assertJsonPath('message', "You can't use your own referral code.");

        $this->redeemAsReferee();
        $this->postJson('/api/v1/referral/apply', ['code' => ReferralService::codeFor($this->referrer)])
            ->assertStatus(422)
            ->assertJsonPath('message', 'You have already linked a referral code.');

        $this->assertSame(1, ReferralReward::count(), 'Races/double redeems must not duplicate attribution');
    }

    public function test_first_completed_booking_credits_the_referrer(): void
    {
        $this->redeemAsReferee();
        $this->refereeBooksAndCompletes();

        $reward = ReferralReward::where('referee_id', $this->referee->user_id)->first();
        $this->assertSame('credited', $reward->status);
        $this->assertNotNull($reward->reward_promo_code);
        $this->assertNotNull($reward->credited_at);

        // The reward promo belongs to the referrer and is immediately usable.
        Sanctum::actingAs($this->referrer, ['*']);
        $res = $this->postJson('/api/v1/promotions/validate', [
            'code' => $reward->reward_promo_code,
            'subtotal' => 1000,
        ]);
        $res->assertOk()->assertJsonPath('valid', true);
        $this->assertEquals(100.0, (float) $res->json('discount_amount'), 'Reward promo is 10% off');

        // Referrer was told, in-app.
        $this->assertTrue(
            Notification::where('user_id', $this->referrer->user_id)
                ->where('title', 'Referral reward unlocked!')
                ->exists()
        );

        // Only one reward row ever - completing more orders never re-issues.
        $this->assertSame(1, ReferralReward::count());
        $this->assertSame(1, (int) $this->getJson('/api/v1/referral')->json('rewards_credited'));
    }

    public function test_completion_without_referral_creates_no_reward(): void
    {
        // No redemption happened - completion must stay reward-free.
        $this->refereeBooksAndCompletes();

        $this->assertSame(0, ReferralReward::count());
    }

    public function test_referral_endpoints_require_authentication(): void
    {
        app('auth')->forgetGuards();

        $this->getJson('/api/v1/referral')->assertStatus(401);
        $this->postJson('/api/v1/referral/apply', ['code' => 'ABCDEFGH'])->assertStatus(401);
    }
}
