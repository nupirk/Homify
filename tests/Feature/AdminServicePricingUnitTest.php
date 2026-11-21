<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Service;
use App\Models\ServicePricing;
use App\Models\User;
use App\Support\PricingUnit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\CreatesApplication;
use Tests\TestCase;

/**
 * service_pricing.unit existed in the schema but the admin form never offered
 * it, so every tier was billed "per hour" whatever the service was. The form
 * now offers duration, piece, nos, unit and complete.
 */
class AdminServicePricingUnitTest extends TestCase
{
    use CreatesApplication, RefreshDatabase;

    private User $admin;

    private Service $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::unguarded(fn () => User::create([
            'user_id' => 1,
            'first_name' => 'Platform',
            'last_name' => 'Admin',
            'email' => 'pricing-admin@homify.local',
            'phone' => '9800000010',
            'password' => bcrypt('secret'),
            'user_type' => 'admin',
            'is_active' => true,
        ]));
        $this->syncUserIdSequence();

        $category = Category::create(['name' => 'Cleaning', 'is_active' => true]);
        $this->service = Service::create([
            'category_id' => $category->category_id,
            'name' => 'Window Cleaning',
            'description' => 'Per-window cleaning',
            'is_active' => true,
            'requires_prepay' => false,
        ]);
    }

    private function payload(array $pricing): array
    {
        return [
            'category_id' => $this->service->category_id,
            'name' => $this->service->name,
            'description' => $this->service->description,
            'is_active' => '1',
            'pricing' => [$pricing],
        ];
    }

    public function test_the_five_billing_bases_are_the_offered_options(): void
    {
        $this->assertSame(
            ['duration', 'piece', 'nos', 'unit', 'complete'],
            array_keys(PricingUnit::OPTIONS)
        );
        $this->assertSame('Nos', PricingUnit::label('nos'));
        $this->assertSame('Complete', PricingUnit::label('complete'));
        // Legacy rows stay readable and keep their own label.
        $this->assertSame('Hour', PricingUnit::label('hour'));
        $this->assertTrue(PricingUnit::isValid('hour'));
        $this->assertFalse(PricingUnit::isValid('per-kilo'));
    }

    public function test_the_edit_form_offers_the_billing_bases_per_tier(): void
    {
        ServicePricing::create([
            'service_id' => $this->service->service_id,
            'tier_name' => 'basic',
            'base_price' => 500,
            'unit' => 'hour',
            'estimated_duration_min' => 60,
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->admin)
            ->get("/admin/services/{$this->service->service_id}/edit")
            ->assertOk()
            ->assertSee('Billing')
            ->assertSee('name="pricing[0][unit]"', false);

        foreach (PricingUnit::OPTIONS as $label) {
            $response->assertSee($label);
        }

        // The legacy value stays selected instead of silently flipping to duration.
        $response->assertSee('<option value="hour"', false);
    }

    public function test_admin_can_save_a_tier_billed_per_piece(): void
    {
        $this->actingAs($this->admin)
            ->put("/admin/services/{$this->service->service_id}", $this->payload([
                'tier_name' => 'basic',
                'base_price' => '350',
                'unit' => 'nos',
            ]))
            ->assertRedirect(route('admin.services.index'));

        $pricing = ServicePricing::where('service_id', $this->service->service_id)->firstOrFail();
        $this->assertSame('nos', $pricing->unit);
        // Per-count pricing does not have to invent a duration.
        $this->assertNull($pricing->estimated_duration_min);
        $this->assertSame('Nos', $pricing->unit_label);
    }

    public function test_a_tier_without_a_unit_defaults_to_duration(): void
    {
        $this->actingAs($this->admin)
            ->put("/admin/services/{$this->service->service_id}", $this->payload([
                'tier_name' => 'basic',
                'base_price' => '900',
            ]))
            ->assertRedirect(route('admin.services.index'));

        $this->assertSame(
            PricingUnit::DEFAULT,
            ServicePricing::where('service_id', $this->service->service_id)->firstOrFail()->unit
        );
    }

    public function test_an_unknown_unit_is_rejected_and_nothing_is_saved(): void
    {
        $this->actingAs($this->admin)
            ->put("/admin/services/{$this->service->service_id}", $this->payload([
                'tier_name' => 'basic',
                'base_price' => '900',
                'unit' => 'per-kilo',
            ]))
            ->assertSessionHasErrors('pricing.0.unit');

        $this->assertSame(0, ServicePricing::where('service_id', $this->service->service_id)->count());
    }

    public function test_editing_a_legacy_tier_keeps_its_original_unit(): void
    {
        $existing = ServicePricing::create([
            'service_id' => $this->service->service_id,
            'tier_name' => 'basic',
            'base_price' => 500,
            'unit' => 'hour',
            'estimated_duration_min' => 60,
            'is_active' => true,
        ]);

        $this->actingAs($this->admin)
            ->put("/admin/services/{$this->service->service_id}", $this->payload([
                'pricing_id' => $existing->pricing_id,
                'tier_name' => 'basic',
                'base_price' => '550',
                'unit' => 'hour',
                'estimated_duration_min' => '60',
            ]))
            ->assertRedirect(route('admin.services.index'));

        $existing->refresh();
        $this->assertSame('hour', $existing->unit);
        $this->assertSame(550.0, (float) $existing->base_price);
    }
}
