<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\CreatesApplication;
use Tests\TestCase;
use App\Models\Category;
use App\Models\PartnerApplication;
use App\Models\Service;
use App\Models\User;

/**
 * Regression: approving a partner application converts it to 'converted'
 * and then redirected back to the review page - whose guard aborts 404 for
 * terminal states. Same trap existed for rejected applications.
 */
class PartnerApplicationFlowTest extends TestCase
{
    use CreatesApplication, RefreshDatabase;

    private User $admin;

    private User $customer;

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
        $this->syncUserIdSequence();

        $this->customer = User::create([
            'first_name' => 'App', 'last_name' => 'Llicant',
            'email' => 'applicant@homify.local',
            'phone' => '9833333333',
            'password' => bcrypt('password'),
            'user_type' => 'customer',
            'is_active' => true,
        ]);
    }

    private function makeApplication(string $status = 'pending'): PartnerApplication
    {
        $application = PartnerApplication::unguarded(fn () => PartnerApplication::create([
            'first_name' => 'App',
            'last_name' => 'Llicant',
            'email' => $this->customer->email,
            'phone' => $this->customer->phone,
            'address' => 'Test Street, Ward 1',
            'city' => 'Kathmandu',
            'state' => 'Bagmati',
            'postal_code' => '44600',
            'experience_years' => 2,
            'previous_work' => [],
            'why_partner' => 'I love cleaning',
            'bio' => 'Fixture bio',
            'hourly_rate' => 500,
            'status' => $status,
        ]));

        $category = Category::create(['name' => 'Cat-' . uniqid(), 'is_active' => true]);
        $service = Service::create([
            'category_id' => $category->category_id,
            'name' => 'App Service',
            'description' => 'Fixture service',
            'is_active' => true,
            'requires_prepay' => false,
        ]);
        $application->services()->attach($service->service_id);

        return $application;
    }

    public function test_approving_application_redirects_to_partner_show_not_404(): void
    {
        $application = $this->makeApplication('under_review');

        $response = $this->actingAs($this->admin)
            ->patch(route('admin.partner-applications.update-status', $application), [
                'status' => 'approved',
            ]);

        $partner = $this->customer->refresh();
        $this->assertSame('partner', $partner->user_type);

        // The old code redirected to the review page, which aborted 404 for
        // converted applications - the exact bug reported.
        $response->assertRedirect(route('admin.partners.show', $partner));
        $this->get(route('admin.partners.show', $partner))->assertOk();

        $this->assertSame('converted', $application->refresh()->status);
    }

    public function test_review_page_for_converted_application_no_longer_404s(): void
    {
        $application = $this->makeApplication('converted');

        $this->actingAs($this->admin)
            ->get(route('admin.partner-applications.review', $application))
            ->assertRedirect(route('admin.partner-applications.show', $application));

        $this->get(route('admin.partner-applications.show', $application))->assertOk();
    }

    public function test_rejecting_application_redirects_to_detail_page(): void
    {
        $application = $this->makeApplication('under_review');

        $response = $this->actingAs($this->admin)
            ->patch(route('admin.partner-applications.update-status', $application), [
                'status' => 'rejected',
                'rejection_reason' => 'Incomplete documents',
            ]);

        $response->assertRedirect(route('admin.partner-applications.show', $application));
        $this->get(route('admin.partner-applications.show', $application))->assertOk();
        $this->assertSame('rejected', $application->refresh()->status);
    }
}
