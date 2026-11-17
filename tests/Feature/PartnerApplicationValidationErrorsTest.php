<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\CreatesApplication;
use Tests\TestCase;
use App\Models\Category;
use App\Models\Service;

/**
 * The apply endpoint used to answer every validation failure with a blanket
 * "Validation failed", so a caller could not tell which field was rejected
 * (a real support case: the mobile form blamed the image upload). The
 * response must now carry the actual problems.
 */
class PartnerApplicationValidationErrorsTest extends TestCase
{
    use CreatesApplication, RefreshDatabase;

    private function validPayload(array $overrides = []): array
    {
        $category = Category::create(['name' => 'Cat-' . uniqid(), 'is_active' => true]);
        $service = Service::create([
            'category_id' => $category->category_id,
            'name' => 'Validation Service',
            'description' => 'Fixture service',
            'is_active' => true,
            'requires_prepay' => false,
        ]);

        return array_merge([
            'first_name' => 'Vali',
            'last_name' => 'Dation',
            'email' => 'validation-' . uniqid() . '@homify.local',
            'phone' => '9844' . random_int(100000, 999999),
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'address' => 'Test Street, Ward 1',
            'city' => 'Kathmandu',
            'state' => 'Bagmati',
            'postal_code' => '44600',
            'bio' => str_repeat('a', 60),
            'experience_years' => 5,
            'hourly_rate' => 500,
            'why_partner' => 'I love cleaning',
            'services' => [$service->service_id],
        ], $overrides);
    }

    public function test_experience_years_above_the_cap_names_the_field(): void
    {
        $response = $this->postJson('/api/v1/partner/apply', $this->validPayload([
            'experience_years' => 2024,
        ]));

        $response->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath(
                'errors.experience_years.0',
                'The experience years field must not be greater than 50.'
            );

        // The headline message must name the problem, not just "Validation failed".
        $this->assertSame(
            'The experience years field must not be greater than 50.',
            $response->json('message')
        );
    }

    public function test_every_failed_field_is_reported(): void
    {
        $payload = $this->validPayload();
        unset($payload['address'], $payload['city']);

        $response = $this->postJson('/api/v1/partner/apply', $payload);

        $response->assertStatus(422);

        $message = $response->json('message');
        $this->assertNotSame('Validation failed', $message);
        $this->assertStringContainsString('The address field is required.', $message);
        $this->assertStringContainsString('The city field is required.', $message);

        $this->assertCount(2, $response->json('errors'));

        // A rejected application must not leave anything behind.
        $this->assertDatabaseCount('partner_applications', 0);
        $this->assertDatabaseCount('users', 0);
    }

    public function test_mismatched_documents_return_422_with_a_clear_message(): void
    {
        $payload = $this->validPayload([
            'documents_base64' => [
                ['name' => 'id.pdf', 'type' => 'application/pdf', 'data' => base64_encode('hello')],
            ],
            'document_types' => ['identity', 'license'],
            'document_names' => ['id.pdf', 'licence.pdf'],
        ]);

        $response = $this->postJson('/api/v1/partner/apply', $payload);

        $response->assertStatus(422)
            ->assertJsonPath('success', false);

        $this->assertStringContainsString(
            'did not match the selected document types',
            $response->json('message')
        );

        $this->assertDatabaseCount('partner_applications', 0);
        $this->assertDatabaseCount('users', 0);
    }
}
