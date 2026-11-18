<?php

namespace Tests\Feature;

use App\Models\ErrorLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Laravel\Sanctum\Sanctum;
use Tests\CreatesApplication;
use Tests\TestCase;

/**
 * Client error/crash ingest contract:
 *  - public but throttled, sanitized, and PII-redacted
 *  - de-duplicated by fingerprint (occurrences bump instead of new rows)
 *  - only super-admins can read the collected reports
 */
class ErrorReportIngestTest extends TestCase
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
            'last_name' => 'System',
            'email' => 'platform@homify.local',
            'phone' => '9800000001',
            'password' => bcrypt('secret'),
            'user_type' => 'admin',
            'is_active' => true,
        ]));

        $this->syncUserIdSequence();

        $this->customer = User::create([
            'first_name' => 'Test', 'last_name' => 'Customer',
            'email' => fake()->unique()->safeEmail(),
            'phone' => '98' . rand(10000000, 99999999),
            'password' => bcrypt('password'), 'user_type' => 'customer', 'is_active' => true,
        ]);
    }

    /** Fresh IP per call so the shared array-cache throttle counter is ours. */
    private function asIp(string $ip): self
    {
        $this->withServerVariables(['REMOTE_ADDR' => $ip]);
        return $this;
    }

    public function test_a_client_error_is_accepted_and_stored(): void
    {
        $this->asIp('10.20.0.1')->postJson('/api/v1/errors', [
            'message' => 'Undefined is not a function',
            'level' => 'error',
            'exception_class' => 'TypeError',
            'file' => 'components/BookingChat.tsx',
            'line' => 42,
            'stack' => 'TypeError: undefined is not a function\n  at BookingChat',
            'screen' => 'BookingChat',
            'platform' => 'android',
            'app_version' => '1.4.0',
            'context' => ['orderId' => 'abc-123', 'retry' => 3],
        ])->assertStatus(202)->assertJsonPath('success', true);

        $log = ErrorLog::firstOrFail();
        $this->assertSame('TypeError', $log->exception_class);
        $this->assertSame('BookingChat', $log->screen);
        $this->assertSame('android', $log->platform);
        $this->assertSame('abc-123', $log->context['orderId']);
        $this->assertSame(1, $log->occurrences);
    }

    public function test_context_secrets_are_redacted(): void
    {
        $this->asIp('10.20.0.2')->postJson('/api/v1/errors', [
            'message' => 'checkout failed',
            'context' => [
                'token' => 'super-secret-token',
                'password' => 'hunter2',
                'otp' => '123456',
                'orderId' => 'keep-me',
            ],
        ])->assertStatus(202);

        $context = ErrorLog::firstOrFail()->context;
        $this->assertSame('[redacted]', $context['token']);
        $this->assertSame('[redacted]', $context['password']);
        $this->assertSame('[redacted]', $context['otp']);
        $this->assertSame('keep-me', $context['orderId']);
    }

    public function test_repeated_identical_errors_collapse_to_one_row(): void
    {
        $payload = [
            'message' => 'Network request failed',
            'exception_class' => 'AxiosError',
            'screen' => 'Cart',
        ];

        $this->asIp('10.20.0.3')->postJson('/api/v1/errors', $payload)->assertStatus(202);
        $this->asIp('10.20.0.3')->postJson('/api/v1/errors', $payload)->assertStatus(202);
        $this->asIp('10.20.0.3')->postJson('/api/v1/errors', $payload)->assertStatus(202);

        $this->assertSame(1, ErrorLog::count());
        $this->assertSame(3, ErrorLog::firstOrFail()->occurrences);
    }

    public function test_errors_differing_only_by_numbers_share_a_fingerprint(): void
    {
        $this->asIp('10.20.0.4')->postJson('/api/v1/errors', [
            'message' => 'Booking 99887 could not be loaded',
            'screen' => 'Bookings',
        ])->assertStatus(202);

        $this->asIp('10.20.0.4')->postJson('/api/v1/errors', [
            'message' => 'Booking 11223 could not be loaded',
            'screen' => 'Bookings',
        ])->assertStatus(202);

        $this->assertSame(1, ErrorLog::count());
        $this->assertSame(2, ErrorLog::firstOrFail()->occurrences);
    }

    public function test_message_is_required(): void
    {
        $this->asIp('10.20.0.5')->postJson('/api/v1/errors', [])
            ->assertStatus(422)
            ->assertJsonPath('success', false);

        $this->assertSame(0, ErrorLog::count());
    }

    public function test_authenticated_report_attaches_the_user(): void
    {
        Sanctum::actingAs($this->customer, ['*']);

        $this->asIp('10.20.0.6')->postJson('/api/v1/errors', [
            'message' => 'something broke',
        ])->assertStatus(202);

        $this->assertSame($this->customer->user_id, ErrorLog::firstOrFail()->user_id);
    }

    public function test_admin_list_requires_authentication(): void
    {
        $this->asIp('10.20.0.7')->getJson('/api/v1/admin/errors')->assertStatus(401);
    }

    public function test_super_admin_can_list_reports(): void
    {
        $this->asIp('10.20.0.8')->postJson('/api/v1/errors', [
            'message' => 'listed error',
            'level' => 'fatal',
        ])->assertStatus(202);

        Sanctum::actingAs($this->admin, ['*']);

        $this->asIp('10.20.0.8')->getJson('/api/v1/admin/errors')
            ->assertOk()
            ->assertJsonPath('data.total', 1)
            ->assertJsonPath('data.data.0.level', 'fatal');
    }

    public function test_a_regular_user_cannot_list_reports(): void
    {
        Sanctum::actingAs($this->customer, ['*']);

        $this->asIp('10.20.0.9')->getJson('/api/v1/admin/errors')->assertStatus(403);
    }

    public function test_ingest_is_rate_limited(): void
    {
        // 30/min per IP for the shared analytics limiter; the 31st must trip.
        $this->withServerVariables(['REMOTE_ADDR' => '10.20.9.9']);

        for ($i = 0; $i < 30; $i++) {
            $response = $this->postJson('/api/v1/errors', ['message' => "error {$i}"]);
            $this->assertNotSame(429, $response->getStatusCode(), "Request {$i} should not be throttled yet");
        }

        $this->postJson('/api/v1/errors', ['message' => 'one too many'])
            ->assertStatus(429);
    }

    public function test_a_client_error_is_mirrored_into_the_server_log(): void
    {
        Log::spy();

        $this->asIp('10.20.0.10')->postJson('/api/v1/errors', [
            'message' => "checkout failed\nsecond line",
            'level' => 'error',
            'screen' => 'Checkout',
        ])->assertStatus(202);

        // storage/logs is where a developer actually looks; a report that only
        // lands in the admin dashboard looks like nothing was logged.
        Log::shouldHaveReceived('error')
            ->once()
            ->withArgs(function (string $message, array $context) {
                return $message === 'Client error reported'
                    && $context['screen'] === 'Checkout'
                    && $context['message'] === 'checkout failed second line';
            });
    }
}
