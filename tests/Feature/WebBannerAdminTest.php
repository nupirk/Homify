<?php

namespace Tests\Feature;

use App\Models\Banner;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\CreatesApplication;
use Tests\TestCase;

/**
 * Blade banner form contract, kept in step with the app admin's Banners screen:
 *  - a creative is mandatory on create and is never lost by an edit that just
 *    changes the copy
 *  - link_value shape depends on the destination (numeric ID, http(s) URL)
 */
class WebBannerAdminTest extends TestCase
{
    use CreatesApplication, RefreshDatabase;

    private function admin(): User
    {
        return User::create([
            'first_name' => 'Web',
            'last_name' => 'Admin',
            'email' => 'webadmin' . rand(1000, 9999) . '@example.com',
            'phone' => '98' . rand(10000000, 99999999),
            'password' => bcrypt('password'),
            'user_type' => 'admin',
            'is_active' => true,
        ]);
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'title' => 'Monsoon cleaning',
            'subtitle' => 'Limited time',
            'placement' => 'home_mid',
            'display_style' => 'banner',
            'link_type' => 'none',
            'link_value' => '',
            'valid_from' => now()->format('Y-m-d\TH:i'),
            'valid_until' => '',
            'sort_order' => '0',
            'is_active' => '1',
            'image_url' => 'https://cdn.example.com/banner.jpg',
        ], $overrides);
    }

    public function test_rejects_non_numeric_service_id(): void
    {
        $this->actingAs($this->admin())
            ->post(route('admin.banners.store'), $this->payload([
                'link_type' => 'service',
                'link_value' => 'abc',
            ]))
            ->assertSessionHasErrors('link_value');
    }

    public function test_rejects_non_url_destination(): void
    {
        $this->actingAs($this->admin())
            ->post(route('admin.banners.store'), $this->payload([
                'link_type' => 'url',
                'link_value' => 'example.com/no-scheme',
            ]))
            ->assertSessionHasErrors('link_value');
    }

    public function test_requires_a_creative_on_create(): void
    {
        $this->actingAs($this->admin())
            ->post(route('admin.banners.store'), $this->payload(['image_url' => '']))
            ->assertSessionHasErrors('image');
    }

    public function test_creates_a_banner_with_valid_input(): void
    {
        $this->actingAs($this->admin())
            ->post(route('admin.banners.store'), $this->payload([
                'link_type' => 'category',
                'link_value' => '3',
            ]))
            ->assertRedirect(route('admin.banners.index'));

        $this->assertDatabaseHas('banners', ['title' => 'Monsoon cleaning', 'link_value' => '3']);
    }

    public function test_update_without_new_image_keeps_the_existing_creative(): void
    {
        $banner = Banner::create([
            'title' => 'Old',
            'placement' => 'cart_top',
            'display_style' => 'banner',
            'link_type' => 'none',
            'image_url' => 'https://cdn.example.com/old.jpg',
            'valid_from' => now(),
            'is_active' => true,
            'sort_order' => 0,
        ]);

        $this->actingAs($this->admin())
            ->put(route('admin.banners.update', $banner), $this->payload([
                'title' => 'Renamed',
                'image_url' => '',
            ]))
            ->assertRedirect(route('admin.banners.index'));

        $banner->refresh();
        $this->assertSame('Renamed', $banner->title);
        $this->assertSame('https://cdn.example.com/old.jpg', $banner->image_url);
    }
}
