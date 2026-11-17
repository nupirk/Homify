<?php

namespace Tests\Feature;

use App\Models\OtpVerification;
use App\Models\User;
use App\Services\SmsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\CreatesApplication;
use Tests\TestCase;

/**
 * Password recovery contract:
 *  - POST /otp/send (type=password_reset) -> /otp/verify -> /auth/reset-password
 *  - unknown phones get the identical generic success on send (no SMS, no
 *    record) and identical generic failures on reset - no user enumeration
 *  - a verified OTP is consumed on use: it can never reset a password twice
 *  - the new password must pass the same rules as registration (min 8 + confirm)
 */
class PasswordResetTest extends TestCase
{
    use CreatesApplication, RefreshDatabase;

    private const PHONE = '9800001111';
    private const UNKNOWN_PHONE = '9876543210';
    private const OLD_PASSWORD = 'oldPassword123';
    private const NEW_PASSWORD = 'newPassword456';

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        // The /otp/send dev code is gated behind EXPOSE_DEV_OTP; these tests
        // read the code to drive the recovery flow.
        config(['app.expose_dev_otp' => true]);

        $this->user = User::create([
            'first_name' => 'Reset',
            'last_name' => 'Customer',
            'email' => 'reset.customer@homify.test',
            'phone' => self::PHONE,
            'password' => Hash::make(self::OLD_PASSWORD),
            'user_type' => 'customer',
            'is_active' => true,
            'phone_verified_at' => now(),
        ]);
    }

    private function requestOtp(string $phone = self::PHONE)
    {
        return $this->postJson('/api/v1/otp/send', [
            'phone' => $phone,
            'type' => 'password_reset',
        ]);
    }

    private function sendAndReadOtp(string $phone = self::PHONE): string
    {
        $response = $this->requestOtp($phone);
        $response->assertOk()->assertJsonPath('success', true);

        $otp = $response->json('data.otp');
        $this->assertNotNull($otp, 'Dev OTP should be returned outside production');

        return $otp;
    }

    private function verifyOtp(string $otp, string $phone = self::PHONE): void
    {
        $this->postJson('/api/v1/otp/verify', [
            'phone' => $phone,
            'otp' => $otp,
            'type' => 'password_reset',
        ])->assertOk()->assertJsonPath('success', true);
    }

    private function reset(
        string $otp,
        string $password,
        string $confirmation,
        string $phone = self::PHONE
    ) {
        return $this->postJson('/api/v1/auth/reset-password', [
            'phone' => $phone,
            'otp' => $otp,
            'password' => $password,
            'password_confirmation' => $confirmation,
        ]);
    }

    private function resetOtpRecord(): OtpVerification
    {
        return OtpVerification::where('phone', self::PHONE)
            ->where('type', 'password_reset')
            ->firstOrFail();
    }

    public function test_password_reset_happy_path_ends_with_login_using_new_password(): void
    {
        $otp = $this->sendAndReadOtp();
        $this->verifyOtp($otp);

        $this->reset($otp, self::NEW_PASSWORD, self::NEW_PASSWORD)
            ->assertOk()
            ->assertJsonPath('success', true);

        $user = $this->user->fresh();
        $this->assertTrue(Hash::check(self::NEW_PASSWORD, $user->password));
        $this->assertFalse(Hash::check(self::OLD_PASSWORD, $user->password));
        $this->assertNotNull($this->resetOtpRecord()->consumed_at);

        // The whole point: the new password actually logs in.
        $this->postJson('/api/v1/login', [
            'email' => self::PHONE,
            'password' => self::NEW_PASSWORD,
        ])->assertOk()->assertJsonPath('success', true);

        $this->postJson('/api/v1/login', [
            'email' => self::PHONE,
            'password' => self::OLD_PASSWORD,
        ])->assertStatus(401);
    }

    public function test_reset_rejects_wrong_otp_and_keeps_existing_password(): void
    {
        $otp = $this->sendAndReadOtp();
        $this->verifyOtp($otp);

        $wrongOtp = $otp === '000000' ? '111111' : '000000';
        $this->reset($wrongOtp, self::NEW_PASSWORD, self::NEW_PASSWORD)
            ->assertStatus(400)
            ->assertJsonPath('success', false);

        $this->assertTrue(Hash::check(self::OLD_PASSWORD, $this->user->fresh()->password));
        // A failed attempt must not burn the verified code.
        $this->assertNull($this->resetOtpRecord()->consumed_at);
    }

    public function test_expired_otp_is_rejected_at_verify_and_reset(): void
    {
        $otp = $this->sendAndReadOtp();
        $this->travel(11)->minutes();

        $this->postJson('/api/v1/otp/verify', [
            'phone' => self::PHONE,
            'otp' => $otp,
            'type' => 'password_reset',
        ])->assertStatus(400);

        // Fresh code: verified in time, then submitted after it expires.
        $otp = $this->sendAndReadOtp();
        $this->verifyOtp($otp);

        $this->travel(11)->minutes();
        $this->reset($otp, self::NEW_PASSWORD, self::NEW_PASSWORD)
            ->assertStatus(400)
            ->assertJsonPath('success', false);

        $this->assertTrue(Hash::check(self::OLD_PASSWORD, $this->user->fresh()->password));
    }

    public function test_verified_otp_cannot_be_reused_after_successful_reset(): void
    {
        $otp = $this->sendAndReadOtp();
        $this->verifyOtp($otp);

        $this->reset($otp, self::NEW_PASSWORD, self::NEW_PASSWORD)
            ->assertOk()
            ->assertJsonPath('success', true);

        // Replay with the very same (verified, correct) code.
        $this->reset($otp, 'anotherPassword789', 'anotherPassword789')
            ->assertStatus(400)
            ->assertJsonPath('success', false);

        $user = $this->user->fresh();
        $this->assertTrue(Hash::check(self::NEW_PASSWORD, $user->password));
        $this->assertFalse(Hash::check('anotherPassword789', $user->password));
    }

    public function test_unknown_phone_send_returns_generic_success_without_creating_otp(): void
    {
        // Real phone: SMS must go out exactly once.
        $this->mock(SmsService::class, function ($mock) {
            $mock->shouldReceive('sendOtp')
                ->once()
                ->with(self::PHONE, \Mockery::type('string'))
                ->andReturn(true);
        });

        $this->sendAndReadOtp();

        // Unknown phone: same 200 + same shape + same message, but no record
        // (and therefore no SMS - any sendOtp() call would fail the mock).
        $response = $this->requestOtp(self::UNKNOWN_PHONE);
        $response->assertOk()->assertJsonPath('success', true);
        $this->assertSame('OTP sent successfully', $response->json('message'));
        $this->assertSame(
            ['phone', 'expires_at', 'expires_in_seconds', 'otp'],
            array_keys($response->json('data'))
        );
        $this->assertSame(self::UNKNOWN_PHONE, $response->json('data.phone'));
        $this->assertMatchesRegularExpression('/^\d{6}$/', (string) $response->json('data.otp'));

        $this->assertNull(
            OtpVerification::where('phone', self::UNKNOWN_PHONE)->first(),
            'No OTP record may be created for an unknown phone'
        );
    }

    public function test_reset_is_indistinguishable_for_unknown_and_unverified_phone(): void
    {
        $payload = fn (string $phone) => [
            'phone' => $phone,
            'otp' => '123456',
            'password' => self::NEW_PASSWORD,
            'password_confirmation' => self::NEW_PASSWORD,
        ];

        $unknown = $this->postJson('/api/v1/auth/reset-password', $payload(self::UNKNOWN_PHONE));
        $knownButUnverified = $this->postJson('/api/v1/auth/reset-password', $payload(self::PHONE));

        $unknown->assertStatus(400);
        $knownButUnverified->assertStatus(400);
        $this->assertSame(
            $knownButUnverified->json(),
            $unknown->json(),
            'Unknown and unverified phones must receive byte-identical responses'
        );

        $this->assertTrue(Hash::check(self::OLD_PASSWORD, $this->user->fresh()->password));
    }

    public function test_new_password_follows_signup_validation_rules(): void
    {
        $otp = $this->sendAndReadOtp();
        $this->verifyOtp($otp);

        // Same rules as register(): min 8 characters...
        $this->reset($otp, 'short7', 'short7')
            ->assertStatus(422)
            ->assertJsonValidationErrors(['password']);

        // ...and confirmation must match.
        $this->reset($otp, self::NEW_PASSWORD, 'different456')
            ->assertStatus(422)
            ->assertJsonValidationErrors(['password']);

        $this->assertTrue(Hash::check(self::OLD_PASSWORD, $this->user->fresh()->password));

        // Validation failures must not consume the verified OTP.
        $this->reset($otp, self::NEW_PASSWORD, self::NEW_PASSWORD)
            ->assertOk()
            ->assertJsonPath('success', true);
        $this->assertTrue(Hash::check(self::NEW_PASSWORD, $this->user->fresh()->password));
    }

    public function test_password_reset_send_is_rate_limited(): void
    {
        foreach (range(1, 3) as $ignored) {
            $this->requestOtp()->assertOk();
        }

        $this->requestOtp()->assertStatus(429);
    }
}
