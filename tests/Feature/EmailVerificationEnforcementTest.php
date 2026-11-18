<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\Sanctum;
use Tests\CreatesApplication;
use Tests\TestCase;

/**
 * Email verification is a gate, not a suggestion:
 *  - registration creates the account but issues no session at all
 *  - login refuses an unverified email and names the address to verify
 *  - a token belonging to an unverified account is rejected at /user
 *  - verifying the emailed code unlocks login
 */
class EmailVerificationEnforcementTest extends TestCase
{
    use CreatesApplication, RefreshDatabase;

    private const EMAIL = 'enforce.otp@homify.test';
    private const PASSWORD = 'secretPassword123';

    protected function setUp(): void
    {
        parent::setUp();

        config(['app.expose_dev_otp' => true]);
        Mail::fake();
    }

    private function register()
    {
        return $this->postJson('/api/v1/register', [
            'first_name' => 'Enforce',
            'last_name' => 'Otp',
            'email' => self::EMAIL,
            'phone' => '9800007777',
            'password' => self::PASSWORD,
            'password_confirmation' => self::PASSWORD,
            'user_type' => 'customer',
        ]);
    }

    public function test_registration_issues_no_session_until_the_email_is_verified(): void
    {
        $response = $this->register();

        $response->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('requires_verification', true)
            ->assertJsonPath('token', null)
            ->assertJsonPath('otp_sent', true);

        $this->assertNull(
            User::where('email', self::EMAIL)->firstOrFail()->email_verified_at,
            'Registration must not mark the email verified'
        );
    }

    public function test_login_is_refused_for_an_unverified_email(): void
    {
        $this->register();

        $this->postJson('/api/v1/login', [
            'email' => self::EMAIL,
            'password' => self::PASSWORD,
        ])->assertStatus(403)
            ->assertJsonPath('success', false)
            ->assertJsonPath('requires_verification', true)
            ->assertJsonPath('email', self::EMAIL)
            ->assertJsonMissingPath('token');
    }

    public function test_verifying_the_emailed_code_unlocks_login(): void
    {
        $otp = $this->register()->json('dev_otp');
        $this->assertIsString($otp);

        $this->postJson('/api/v1/otp/verify', [
            'channel' => 'email',
            'email' => self::EMAIL,
            'otp' => $otp,
            'type' => 'registration',
        ])->assertOk()->assertJsonPath('success', true);

        $login = $this->postJson('/api/v1/login', [
            'email' => self::EMAIL,
            'password' => self::PASSWORD,
        ])->assertOk()->assertJsonPath('success', true);

        $this->assertNotNull($login->json('user.email_verified_at'));
        $this->assertNotEmpty($login->json('token'));
    }

    public function test_a_token_for_an_unverified_account_is_rejected(): void
    {
        $user = User::create([
            'first_name' => 'Stale',
            'last_name' => 'Session',
            'email' => 'stale.session@homify.test',
            'phone' => '9800008888',
            'password' => Hash::make(self::PASSWORD),
            'user_type' => 'customer',
            'is_active' => true,
        ]);

        Sanctum::actingAs($user, ['*']);

        $this->getJson('/api/v1/user')
            ->assertStatus(403)
            ->assertJsonPath('requires_verification', true);
    }
}
