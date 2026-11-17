<?php

namespace Tests\Feature;

use App\Mail\OtpCodeMail;
use App\Models\OtpVerification;
use App\Models\User;
use App\Services\SmsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Tests\CreatesApplication;
use Tests\TestCase;

/**
 * Email OTP channel contract:
 *  - `channel=email` stores an email-channel record and delivers a mailable
 *  - verifying an email registration stamps email_verified_at (not phone)
 *  - the phone channel stays the default with its frozen response shape
 *  - password recovery stays anti-enumeration on the email channel too
 */
class EmailOtpTest extends TestCase
{
    use CreatesApplication, RefreshDatabase;

    private const EMAIL = 'email.otp@urban.test';

    protected function setUp(): void
    {
        parent::setUp();

        config(['app.expose_dev_otp' => true]);
        Mail::fake();
    }

    private function makeUser(array $overrides = []): User
    {
        return User::create(array_merge([
            'first_name' => 'Email',
            'last_name' => 'Otp',
            'email' => self::EMAIL,
            'phone' => '9800004444',
            'password' => Hash::make('secretPassword123'),
            'user_type' => 'customer',
            'is_active' => true,
        ], $overrides));
    }

    public function test_email_channel_creates_a_record_and_sends_the_mailable(): void
    {
        $this->makeUser();

        $response = $this->postJson('/api/v1/otp/send', [
            'channel' => 'email',
            'email' => self::EMAIL,
            'type' => 'password_reset',
        ]);

        $response->assertOk()->assertJsonPath('success', true);
        $this->assertSame(self::EMAIL, $response->json('data.email'));

        $record = OtpVerification::where('email', self::EMAIL)->latest('id')->first();
        $this->assertNotNull($record);
        $this->assertSame('email', $record->channel);
        $this->assertNull($record->phone);

        Mail::assertSent(OtpCodeMail::class, function (OtpCodeMail $mail) use ($record) {
            return $mail->hasTo(self::EMAIL) && $mail->code === $record->otp;
        });
    }

    public function test_email_registration_verification_marks_email_not_phone(): void
    {
        $user = $this->makeUser(['email_verified_at' => null, 'phone_verified_at' => null]);

        $otp = $this->postJson('/api/v1/otp/send', [
            'channel' => 'email',
            'email' => self::EMAIL,
            'type' => 'registration',
        ])->assertOk()->json('data.otp');
        $this->assertNotNull($otp, 'Dev OTP should be returned outside production');

        $this->postJson('/api/v1/otp/verify', [
            'channel' => 'email',
            'email' => self::EMAIL,
            'otp' => $otp,
            'type' => 'registration',
        ])->assertOk()->assertJsonPath('success', true);

        $user->refresh();
        $this->assertNotNull($user->email_verified_at);
        $this->assertNull($user->phone_verified_at, 'Email verification must not verify the phone');
    }

    public function test_wrong_email_code_is_rejected_and_does_not_verify(): void
    {
        $user = $this->makeUser(['email_verified_at' => null]);

        $otp = $this->postJson('/api/v1/otp/send', [
            'channel' => 'email',
            'email' => self::EMAIL,
            'type' => 'registration',
        ])->json('data.otp');

        $wrong = $otp === '000000' ? '111111' : '000000';

        $this->postJson('/api/v1/otp/verify', [
            'channel' => 'email',
            'email' => self::EMAIL,
            'otp' => $wrong,
            'type' => 'registration',
        ])->assertStatus(400)->assertJsonPath('success', false);

        $this->assertNull($user->fresh()->email_verified_at);
    }

    public function test_sending_to_an_already_verified_email_is_rejected(): void
    {
        $this->makeUser(['email_verified_at' => now()]);

        $this->postJson('/api/v1/otp/send', [
            'channel' => 'email',
            'email' => self::EMAIL,
            'type' => 'registration',
        ])->assertStatus(400)->assertJsonPath('success', false);

        Mail::assertNothingSent();
    }

    public function test_unknown_email_reset_is_generic_with_no_record_or_mail(): void
    {
        $response = $this->postJson('/api/v1/otp/send', [
            'channel' => 'email',
            'email' => 'nobody.here@urban.test',
            'type' => 'password_reset',
        ]);

        $response->assertOk()->assertJsonPath('success', true);
        $this->assertSame('OTP sent successfully', $response->json('message'));
        $this->assertSame(
            ['email', 'expires_at', 'expires_in_seconds', 'otp'],
            array_keys($response->json('data'))
        );

        $this->assertNull(OtpVerification::where('email', 'nobody.here@urban.test')->first());
        Mail::assertNothingSent();
    }

    public function test_email_resend_obeys_the_30_second_cooldown(): void
    {
        $this->makeUser();

        $this->postJson('/api/v1/otp/send', [
            'channel' => 'email',
            'email' => self::EMAIL,
            'type' => 'password_reset',
        ])->assertOk();

        $this->postJson('/api/v1/otp/resend', [
            'channel' => 'email',
            'email' => self::EMAIL,
            'type' => 'password_reset',
        ])->assertStatus(429)->assertJsonPath('success', false);
    }

    public function test_phone_channel_remains_the_default_with_a_frozen_shape(): void
    {
        $this->mock(SmsService::class, function ($mock) {
            $mock->shouldReceive('sendOtp')->once()->andReturn(true);
        });

        $this->makeUser(['phone' => '9800005555']);

        // No `channel` key -> phone path.
        $response = $this->postJson('/api/v1/otp/send', [
            'phone' => '9800005555',
            'type' => 'password_reset',
        ]);

        $response->assertOk()->assertJsonPath('success', true);
        $this->assertSame(
            ['phone', 'expires_at', 'expires_in_seconds', 'otp'],
            array_keys($response->json('data'))
        );
        Mail::assertNothingSent();
    }
}
