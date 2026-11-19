<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\PartnerApplication;
use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\CreatesApplication;
use Tests\TestCase;

/**
 * The mobile form submits KYC documents as base64 JSON (the FormData path is
 * legacy). That path had no test at all, so a payload the app really sends was
 * never exercised end to end.
 */
class PartnerApplicationSubmissionTest extends TestCase
{
    use CreatesApplication, RefreshDatabase;

    private const EMAIL = 'applicant.mobile@homify.test';
    private const PHONE = '9812345670';

    private int $serviceId;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();
        Storage::fake('private');

        $category = Category::create(['name' => 'Plumbing', 'is_active' => true]);
        $this->serviceId = Service::create([
            'category_id' => $category->category_id,
            'name' => 'Pipe repair',
            'description' => 'Fixing pipes',
            'is_active' => true,
            'requires_prepay' => false,
        ])->service_id;
    }

    /** A minimal one-page PDF, base64 encoded the way the app sends it. */
    private function base64Pdf(): string
    {
        $pdf = "%PDF-1.4\n1 0 obj<</Type/Catalog/Pages 2 0 R>>endobj\n"
            . "2 0 obj<</Type/Pages/Kids[3 0 R]/Count 1>>endobj\n"
            . "3 0 obj<</Type/Page/Parent 2 0 R/MediaBox[0 0 200 200]>>endobj\n"
            . "trailer<</Root 1 0 R>>\n%%EOF\n";

        return base64_encode($pdf);
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'first_name' => 'Mobile',
            'last_name' => 'Applicant',
            'email' => self::EMAIL,
            'phone' => self::PHONE,
            'password' => 'secretPassword123',
            'password_confirmation' => 'secretPassword123',
            'emergency_contact' => '9800000000',
            'address' => 'Ward 4, Lazimpat',
            'city' => 'Kathmandu',
            'state' => 'Bagmati',
            'postal_code' => '44600',
            'bio' => 'I have spent eight years repairing residential plumbing and electrical faults across Kathmandu.',
            'experience_years' => 8,
            'hourly_rate' => 900,
            'why_partner' => 'I want steady work with customers who need reliable same-day repairs.',
            'availability_hours' => ['monday' => ['09:00', '18:00']],
            'portfolio_links' => null,
            'references' => null,
            'services' => [$this->serviceId],
            'documents_base64' => [
                ['name' => 'citizenship.pdf', 'type' => 'application/pdf', 'data' => $this->base64Pdf()],
            ],
            'document_types' => ['identity'],
            'document_names' => ['citizenship.pdf'],
            'document_expirations' => [null],
        ], $overrides);
    }

    public function test_the_mobile_base64_payload_is_accepted(): void
    {
        $response = $this->postJson('/api/v1/partner/apply', $this->payload());

        $response->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.application_status', 'pending');

        $application = PartnerApplication::where('email', self::EMAIL)->firstOrFail();
        $this->assertSame('pending', $application->status);
        $this->assertCount(1, $application->documents ?? []);
        $this->assertSame('identity', $application->documents[0]['type']);
        Storage::disk('private')->assertExists($application->documents[0]['file_path']);
    }

    /**
     * The customer profile offers "Apply for partner" to people who already
     * have an account. That submission must attach to the existing account -
     * not collide with it, and not ask for a password it already has.
     */
    public function test_a_signed_in_customer_applies_without_a_second_account(): void
    {
        $customer = User::create([
            'first_name' => 'Signed',
            'last_name' => 'Customer',
            'email' => 'signed.in@homify.test',
            'phone' => self::PHONE,
            'password' => bcrypt('secretPassword123'),
            'user_type' => 'customer',
            'is_active' => true,
        ]);

        $response = $this->actingAs($customer, 'sanctum')->postJson('/api/v1/partner/apply', $this->payload([
            // The account's own email must win over whatever the form posts.
            'email' => 'someone.else@homify.test',
            'password' => null,
            'password_confirmation' => null,
        ]));

        $response->assertCreated()->assertJsonPath('data.application_status', 'pending');

        $application = PartnerApplication::where('email', $customer->email)->firstOrFail();
        $this->assertSame('pending', $application->status);

        // No second account, and the existing one is flagged for review.
        $this->assertSame(1, User::count());
        $this->assertSame('pending', $customer->fresh()->partner_application_status);
    }

    public function test_a_mismatched_document_list_is_rejected_with_a_readable_error(): void
    {
        $response = $this->postJson('/api/v1/partner/apply', $this->payload([
            'document_types' => ['identity', 'license'],
        ]));

        $response->assertStatus(422)
            ->assertJsonPath('success', false);

        $this->assertNotEmpty($response->json('message'));
    }

    public function test_reapplying_after_rejection_is_allowed(): void
    {
        $this->postJson('/api/v1/partner/apply', $this->payload())->assertCreated();

        PartnerApplication::where('email', self::EMAIL)->update([
            'status' => 'rejected',
            'rejection_reason' => 'Documents unclear',
            'rejected_at' => now(),
        ]);

        $this->postJson('/api/v1/partner/apply', $this->payload())->assertCreated();

        $this->assertSame(1, PartnerApplication::where('email', self::EMAIL)->count());
        $this->assertSame('pending', PartnerApplication::where('email', self::EMAIL)->value('status'));
    }
}
