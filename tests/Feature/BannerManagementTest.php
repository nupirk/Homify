<?php

namespace Tests\Feature;

use App\Models\Banner;
use App\Models\Promotion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\CreatesApplication;
use Tests\TestCase;

/**
 * Marketing banner CMS contract:
 *  - the public feed only exposes active banners inside their window
 *  - super-admin CRUD manages multipart or JSON images on the public disk
 *  - placement/link enums are validated and link_value is required for links
 *  - replacing/deleting a banner also removes the file we own for it
 */
class BannerManagementTest extends TestCase
{
    use CreatesApplication, RefreshDatabase;

    private User $admin;
    private User $customer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::create([
            'first_name' => 'CMS',
            'last_name' => 'Admin',
            'email' => fake()->unique()->safeEmail(),
            'phone' => '98' . rand(10000000, 99999999),
            'password' => bcrypt('password'),
            'user_type' => 'admin',
            'is_active' => true,
        ]);

        $this->customer = User::create([
            'first_name' => 'Banner',
            'last_name' => 'Customer',
            'email' => fake()->unique()->safeEmail(),
            'phone' => '98' . rand(10000000, 99999999),
            'password' => bcrypt('password'),
            'user_type' => 'customer',
            'is_active' => true,
        ]);

        // A promo banner may only advertise a code customers can redeem.
        Promotion::create([
            'promo_code' => 'MONSOON40',
            'title' => 'Monsoon 40% off',
            'discount_percent' => 40,
            'promo_type' => 'percentage',
            'valid_from' => now()->subDay(),
            'valid_until' => now()->addDays(7),
            'is_active' => true,
        ]);
    }

    private function makeBanner(array $overrides = []): Banner
    {
        return Banner::create(array_merge([
            'title' => 'Test Banner',
            'placement' => 'home_mid',
            'valid_from' => now()->subDay(),
            'valid_until' => now()->addDays(7),
            'is_active' => true,
            'sort_order' => 0,
        ], $overrides));
    }

    private function adminPayload(array $overrides = []): array
    {
        return array_merge([
            'title' => 'Monsoon Sale',
            'subtitle' => 'Up to 40% off',
            'placement' => 'home_mid',
            'link_type' => 'promo',
            'link_value' => 'MONSOON40',
            'valid_from' => now()->subDay()->format('Y-m-d H:i:s'),
            'valid_until' => now()->addDays(7)->format('Y-m-d H:i:s'),
            'sort_order' => 3,
            'is_active' => true,
        ], $overrides);
    }

    public function test_public_feed_excludes_inactive_expired_and_future_banners(): void
    {
        $openEnded = $this->makeBanner(['title' => 'Open ended', 'valid_until' => null, 'sort_order' => 1]);
        $live = $this->makeBanner(['title' => 'Live', 'sort_order' => 2]);
        $this->makeBanner(['title' => 'Switched off', 'is_active' => false, 'sort_order' => 3]);
        $this->makeBanner(['title' => 'Expired', 'valid_until' => now()->subDay(), 'sort_order' => 4]);
        $this->makeBanner(['title' => 'Not yet', 'valid_from' => now()->addDay(), 'sort_order' => 5]);

        Sanctum::actingAs($this->customer, ['*']);
        $res = $this->getJson('/api/v1/banners');

        $res->assertOk()->assertJsonPath('success', true)->assertJsonCount(2, 'data.banners');
        $this->assertSame(
            [$openEnded->banner_id, $live->banner_id],
            array_column($res->json('data.banners'), 'banner_id')
        );
        $res->assertJsonStructure([
            'success',
            'data' => ['banners' => [[
                'banner_id',
                'title',
                'subtitle',
                'image_url',
                'placement',
                'link_type',
                'link_value',
                'sort_order',
            ]]],
        ]);
    }

    public function test_public_feed_is_readable_by_guests(): void
    {
        // The banner feed is marketing: guests see the same active banners as
        // signed-in customers, otherwise the signed-out home has no campaign.
        app('auth')->forgetGuards();

        $live = $this->makeBanner(['title' => 'Guest visible', 'sort_order' => 1]);
        $this->makeBanner(['title' => 'Switched off', 'is_active' => false, 'sort_order' => 2]);

        $res = $this->getJson('/api/v1/banners');

        $res->assertOk()->assertJsonPath('success', true);
        $this->assertSame(
            [$live->banner_id],
            array_column($res->json('data.banners'), 'banner_id')
        );
    }

    public function test_admin_can_create_banner_with_uploaded_image(): void
    {
        Storage::fake('public');

        Sanctum::actingAs($this->admin, ['*']);
        $res = $this->post('/api/v1/admin/banners', $this->adminPayload([
            'image' => UploadedFile::fake()->image('hero.jpg', 640, 320),
        ]));

        $res->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.banner.title', 'Monsoon Sale')
            ->assertJsonPath('data.banner.placement', 'home_mid');

        $url = $res->json('data.banner.image_url');
        $this->assertStringStartsWith('http', $url);
        $this->assertStringContainsString('/storage/banners/banner_', $url);
        Storage::disk('public')->assertExists('banners/' . basename($url));
        $this->assertDatabaseHas('banners', [
            'banner_id' => $res->json('data.banner.banner_id'),
            'placement' => 'home_mid',
        ]);
    }

    public function test_admin_can_create_banner_with_json_image_url(): void
    {
        Sanctum::actingAs($this->admin, ['*']);
        $res = $this->postJson('/api/v1/admin/banners', $this->adminPayload([
            'placement' => 'explore_top',
            'image_url' => 'https://cdn.example.com/explore.png',
            'link_type' => 'url',
            'link_value' => 'https://example.com/deals',
        ]));

        $res->assertCreated()
            ->assertJsonPath('data.banner.image_url', 'https://cdn.example.com/explore.png')
            ->assertJsonPath('data.banner.link_type', 'url');
    }

    public function test_admin_banner_create_rejects_unknown_placement_and_link_targets(): void
    {
        Sanctum::actingAs($this->admin, ['*']);

        $this->postJson('/api/v1/admin/banners', $this->adminPayload(['placement' => 'footer_hero']))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['placement']);

        $this->postJson('/api/v1/admin/banners', $this->adminPayload(['link_type' => 'deeplink']))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['link_type']);

        $this->postJson('/api/v1/admin/banners', $this->adminPayload([
            'link_type' => 'service',
            'link_value' => null,
        ]))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['link_value']);

        $this->assertDatabaseCount('banners', 0);
    }

    public function test_admin_banner_create_requires_valid_until_after_valid_from(): void
    {
        Sanctum::actingAs($this->admin, ['*']);

        $this->postJson('/api/v1/admin/banners', $this->adminPayload([
            'valid_from' => now()->addDays(2)->format('Y-m-d H:i:s'),
            'valid_until' => now()->format('Y-m-d H:i:s'),
        ]))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['valid_until']);
    }

    public function test_admin_banner_list_returns_every_banner_in_order(): void
    {
        $this->makeBanner(['title' => 'Second', 'sort_order' => 5]);
        $this->makeBanner(['title' => 'First', 'sort_order' => 1, 'is_active' => false]);

        Sanctum::actingAs($this->admin, ['*']);
        $res = $this->getJson('/api/v1/admin/banners');

        $res->assertOk()->assertJsonCount(2, 'data.banners');
        $this->assertSame(
            ['First', 'Second'],
            array_column($res->json('data.banners'), 'title')
        );
    }

    public function test_admin_can_update_banner_replacing_image_and_deleting_old_file(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('banners/banner_old_123.png', 'old-bytes');
        $banner = $this->makeBanner(['image_url' => asset('storage/banners/banner_old_123.png')]);

        Sanctum::actingAs($this->admin, ['*']);
        $res = $this->put("/api/v1/admin/banners/{$banner->banner_id}", $this->adminPayload([
            'title' => 'Renamed Banner',
            'image' => UploadedFile::fake()->image('new.png', 320, 160),
        ]));

        $res->assertOk()->assertJsonPath('data.banner.title', 'Renamed Banner');

        $newUrl = $res->json('data.banner.image_url');
        $this->assertStringNotContainsString('banner_old_123', $newUrl);
        Storage::disk('public')->assertMissing('banners/banner_old_123.png');
        Storage::disk('public')->assertExists('banners/' . basename($newUrl));
    }

    public function test_admin_banner_update_keeps_existing_image_when_no_image_sent(): void
    {
        Storage::fake('public');
        $existingUrl = asset('storage/banners/banner_keep_9.png');
        $banner = $this->makeBanner(['image_url' => $existingUrl]);

        Sanctum::actingAs($this->admin, ['*']);
        $this->put("/api/v1/admin/banners/{$banner->banner_id}", $this->adminPayload([
            'title' => 'Title Only Change',
        ]))
            ->assertOk()
            ->assertJsonPath('data.banner.image_url', $existingUrl);

        $this->assertSame('Title Only Change', $banner->fresh()->title);
    }

    public function test_admin_can_delete_banner_and_its_public_file(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('banners/banner_delete_1.png', 'bytes');
        $banner = $this->makeBanner(['image_url' => asset('storage/banners/banner_delete_1.png')]);

        Sanctum::actingAs($this->admin, ['*']);
        $this->deleteJson("/api/v1/admin/banners/{$banner->banner_id}")
            ->assertOk()
            ->assertJsonPath('success', true);

        Storage::disk('public')->assertMissing('banners/banner_delete_1.png');
        $this->assertDatabaseMissing('banners', ['banner_id' => $banner->banner_id]);
    }

    public function test_admin_banner_routes_reject_customers_and_guests(): void
    {
        Sanctum::actingAs($this->customer, ['*']);
        $this->getJson('/api/v1/admin/banners')->assertStatus(403);
        $this->postJson('/api/v1/admin/banners', $this->adminPayload())->assertStatus(403);

        app('auth')->forgetGuards();
        $this->getJson('/api/v1/admin/banners')->assertStatus(401);
    }

    public function test_promo_banner_rejects_a_code_no_customer_can_redeem(): void
    {
        // The exact production failure: a live poster advertising a code that
        // was switched off, so checkout answered "no longer active".
        Promotion::create([
            'promo_code' => 'SWITCHEDOFF',
            'title' => 'Switched off',
            'discount_percent' => 10,
            'promo_type' => 'percentage',
            'valid_from' => now()->subDay(),
            'valid_until' => now()->addDays(7),
            'is_active' => false,
        ]);

        Sanctum::actingAs($this->admin, ['*']);

        $inactive = $this->post('/api/v1/admin/banners', $this->adminPayload([
            'link_value' => 'SWITCHEDOFF',
        ]));
        $inactive->assertStatus(422)->assertJsonValidationErrors('link_value');
        $this->assertStringContainsString('inactive', $inactive->json('errors.link_value.0'));

        $unknown = $this->post('/api/v1/admin/banners', $this->adminPayload([
            'link_value' => 'GHOSTCODE',
        ]));
        $unknown->assertStatus(422)->assertJsonValidationErrors('link_value');

        $this->assertDatabaseCount('banners', 0);
    }

    public function test_promo_banner_accepts_a_live_code(): void
    {
        Storage::fake('public');

        Sanctum::actingAs($this->admin, ['*']);
        $this->post('/api/v1/admin/banners', $this->adminPayload([
            'image' => UploadedFile::fake()->image('offer.jpg', 640, 320),
        ]))
            ->assertCreated()
            ->assertJsonPath('data.banner.link_value', 'MONSOON40');
    }
}
