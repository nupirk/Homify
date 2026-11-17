<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\CreatesApplication;
use Tests\TestCase;
use App\Models\Category;
use App\Models\PartnerProfile;
use App\Models\Service;
use App\Models\User;

/**
 * The superadmin partner update was written against a nonexistent schema
 * (users.name, users.id, services.id, profile address columns) so every
 * save failed - unique validation matched the partner's own email/phone,
 * services.* referenced services.id which does not exist.
 */
class SuperAdminPartnerUpdateTest extends TestCase
{
    use CreatesApplication, RefreshDatabase;

    private User $admin;

    private User $partner;

    private Service $serviceA;

    private Service $serviceB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::unguarded(fn () => User::create([
            'user_id' => 1,
            'first_name' => 'Platform',
            'last_name' => 'Admin',
            'email' => 'platform@homify.local',
            'phone' => '9800000001',
            'password' => bcrypt('secret'),
            'user_type' => 'admin',
            'is_active' => true,
        ]));
        \Illuminate\Support\Facades\DB::statement(
            "SELECT setval('users_user_id_seq', GREATEST((SELECT COALESCE(MAX(user_id), 1) FROM users), 1))"
        );

        $this->partner = User::create([
            'first_name' => 'Old', 'last_name' => 'Name',
            'email' => 'old@homify.local',
            'phone' => '9811111111',
            'password' => bcrypt('password'),
            'user_type' => 'partner',
            'is_active' => true,
        ]);
        PartnerProfile::unguarded(fn () => PartnerProfile::create([
            'partner_id' => $this->partner->user_id,
            'status' => 'pending_verification',
        ]));

        $category = Category::create(['name' => 'Cat', 'is_active' => true]);
        $this->serviceA = Service::create([
            'category_id' => $category->category_id, 'name' => 'A',
            'description' => 'A', 'is_active' => true, 'requires_prepay' => false,
        ]);
        $this->serviceB = Service::create([
            'category_id' => $category->category_id, 'name' => 'B',
            'description' => 'B', 'is_active' => true, 'requires_prepay' => false,
        ]);

        $this->partner->services()->attach($this->serviceA->service_id);
    }

    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'first_name' => 'New',
            'last_name' => 'Name',
            // Same email/phone the partner already has: must NOT trip unique validation.
            'email' => $this->partner->email,
            'phone' => $this->partner->phone,
            'status' => 'active',
            'business_name' => 'New Biz',
            'business_address' => 'Jhamsikhel, Lalitpur',
            'bio' => 'Pro cleaner',
            'experience_years' => '5',
            'hourly_rate' => '750.50',
            'services' => [$this->serviceB->service_id],
            'is_active' => '1',
        ], $overrides);
    }

    public function test_admin_can_update_partner_with_unchanged_email_and_phone(): void
    {
        $response = $this->actingAs($this->admin)
            ->put("/admin/partners/{$this->partner->user_id}", $this->validPayload());

        $response->assertRedirect(route('admin.partners.show', $this->partner));

        $this->partner->refresh();
        $this->assertSame('active', $this->partner->partnerProfile->status);
        $this->assertSame('New Biz', $this->partner->partnerProfile->business_name);
        $this->assertSame(750.50, (float) $this->partner->partnerProfile->hourly_rate);
        $this->assertSame([5], [$this->partner->partnerProfile->experience_years]);
        $this->assertTrue($this->partner->services->pluck('service_id')->contains($this->serviceB->service_id));
        $this->assertFalse($this->partner->services->pluck('service_id')->contains($this->serviceA->service_id));
    }

    public function test_update_deactivates_partner_when_checkbox_absent(): void
    {
        $payload = $this->validPayload();
        unset($payload['is_active']);

        $response = $this->actingAs($this->admin)
            ->put("/admin/partners/{$this->partner->user_id}", $payload);

        $response->assertRedirect(route('admin.partners.show', $this->partner));
        $this->assertFalse((bool) $this->partner->refresh()->is_active);
    }

    public function test_update_password_is_hashed_and_blank_password_kept(): void
    {
        $currentHash = $this->partner->password;

        $this->actingAs($this->admin)
            ->put("/admin/partners/{$this->partner->user_id}", $this->validPayload())
            ->assertRedirect();

        $this->assertSame($currentHash, $this->partner->refresh()->password);

        $this->actingAs($this->admin)
            ->put("/admin/partners/{$this->partner->user_id}", $this->validPayload([
                'password' => 'newpassword123',
                'password_confirmation' => 'newpassword123',
            ]))
            ->assertRedirect();

        $this->assertNotSame($currentHash, $this->partner->refresh()->password);
    }

    public function test_update_still_blocks_email_taken_by_another_user(): void
    {
        $other = User::create([
            'first_name' => 'Other', 'last_name' => 'User',
            'email' => 'taken@homify.local',
            'phone' => '9822222222',
            'password' => bcrypt('password'),
            'user_type' => 'customer',
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->admin)
            ->put("/admin/partners/{$this->partner->user_id}", $this->validPayload([
                'email' => $other->email,
            ]));

        $response->assertSessionHasErrors('email');
    }
}
