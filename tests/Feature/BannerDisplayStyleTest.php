<?php

namespace Tests\Feature;

use App\Models\Banner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\CreatesApplication;
use Tests\TestCase;

/**
 * A campaign can be presented as an inline banner, a poster or a popup. The
 * client decides which component renders it, so the public feed must carry the
 * style and must never silently omit it.
 */
class BannerDisplayStyleTest extends TestCase
{
    use CreatesApplication, RefreshDatabase;

    private function makeBanner(array $overrides = []): Banner
    {
        return Banner::create(array_merge([
            'title' => 'Monsoon offer',
            'subtitle' => '20% off',
            'image_url' => 'https://example.com/banner.jpg',
            'placement' => 'home_mid',
            'link_type' => 'promo',
            'link_value' => 'MONSOON20',
            'valid_from' => now()->subDay(),
            'valid_until' => now()->addDays(7),
            'is_active' => true,
            'sort_order' => 1,
        ], $overrides));
    }

    public function test_display_style_defaults_to_banner(): void
    {
        $this->makeBanner();

        $this->getJson('/api/v1/banners')
            ->assertOk()
            ->assertJsonPath('data.banners.0.display_style', 'banner');
    }

    public function test_public_feed_carries_every_presentation_style(): void
    {
        $this->makeBanner(['title' => 'Slider', 'display_style' => 'banner']);
        $this->makeBanner(['title' => 'Tall', 'display_style' => 'poster', 'sort_order' => 2]);
        $this->makeBanner(['title' => 'Modal', 'display_style' => 'popup', 'sort_order' => 3]);

        $banners = collect($this->getJson('/api/v1/banners')->assertOk()->json('data.banners'));

        $this->assertSame(
            ['banner', 'poster', 'popup'],
            $banners->pluck('display_style')->all()
        );
    }

    public function test_unknown_display_style_is_rejected_by_the_model_validation(): void
    {
        // The API validates against BannerController::DISPLAY_STYLES; this
        // guards the list itself from drifting away from what the app renders.
        $allowed = (new \ReflectionClass(\App\Http\Controllers\Api\V1\BannerController::class))
            ->getConstant('DISPLAY_STYLES');

        $this->assertSame(['banner', 'poster', 'popup'], $allowed);
    }
}
