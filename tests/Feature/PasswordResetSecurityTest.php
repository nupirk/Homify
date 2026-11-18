<?php

namespace Tests\Feature;

use App\Mail\PasswordChangedMail;
use App\Models\OtpVerification;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Tests\CreatesApplication;
use Tests\TestCase;

/**
 * Password recovery must not leave a door open behind it:
 *  - a reset kills every existing session
 *  - every outstanding reset code dies with it
 *  - a code sent to the mailbox also verifies an unverified email
 *  - the current password cannot be "reset" to itself
 *  - codes are stored hashed and the owner is notified
 */
class PasswordResetSecurityTest extends TestCase
{
    use CreatesApplication, RefreshDatabase;

    private const EMAIL = 'reset.security@homify.test';
    private const OLD_PASSWORD = 'oldPassword123';
    private const NEW_PASSWORD = 'newPassword456';

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        config(['app.expose_dev_otp' => true]);
        Mail::fake();

        $this->user = User::create([
            'first_name' => 'Reset',
            'last_name' => 'Security',
            'email' => self::EMAIL,
            'phone' => '9800009999',
            'password' => Hash::make(self::OLD_PASSWORD),
            'user_type' => 'customer',
            'is_active' => true,
            'email_verified_at' => now(),
        ]);
    }

    private function requestCode(string $email = self::EMAIL): string
    {
        $response = $this->postJson('/api/v1/otp/send', [
            'channel' => 'email',
            'email' => $email,
            'type' => 'password_reset',
        ])->assertOk();

        $code = $response->json('data.otp');
        $this->assertIsString($code);

        return $code;
    }

    private function verifyCode(string $code, string $email = self::EMAIL): void
    {
        $this->postJson('/api/v1/otp/verify', [
            'channel' => 'email',
            'email' => $email,
            'otp' => $code,
            'type' => 'password_reset',
        ])->assertOk()->assertJsonPath('success', true);
    }

    private function reset(string $code, string $password = self::NEW_PASSWORD, string $email = self::EMAIL)
    {
        return $this->postJson('/api/v1/auth/reset-password', [
            'email' => $email,
            'otp' => $code,
            'password' => $password,
            'password_confirmation' => $password,
        ]);
    }

    public function test_a_reset_revokes_every_existing_session(): void
    {
        $this->user->createToken('phone');
        $this->user->createToken('tablet');
        $this->assertSame(2, $this->user->tokens()->count());

        $code = $this->requestCode();
        $this->verifyCode($code);
        $this->reset($code)->assertOk();

        $this->assertSame(
            0,
            $this->user->fresh()->tokens()->count(),
            'A stolen token must not survive the password reset'
        );
    }

    public function test_a_reset_consumes_every_outstanding_code(): void
    {
        $first = $this->requestCode();
        $this->verifyCode($first);

        // A newer code supersedes the verified-but-unused one.
        $second = $this->requestCode();
        $this->verifyCode($second);

        $this->reset($second)->assertOk();

        $this->assertSame(
            0,
            OtpVerification::where('email', self::EMAIL)
                ->where('type', 'password_reset')
                ->whereNull('consumed_at')
                ->count(),
            'No reset code may remain usable after a successful reset'
        );

        // Replaying the first code is refused.
        $this->reset($first, 'anotherPassword789')->assertStatus(400);
    }

    public function test_a_reset_verifies_an_unverified_email(): void
    {
        $this->user->update(['email_verified_at' => null]);

        $code = $this->requestCode();
        $this->verifyCode($code);
        $this->reset($code)->assertOk();

        $this->assertNotNull(
            $this->user->fresh()->email_verified_at,
            'Redeeming an emailed reset code proves ownership of the mailbox'
        );

        // ...and that account can sign in without a second code.
        $this->postJson('/api/v1/login', [
            'email' => self::EMAIL,
            'password' => self::NEW_PASSWORD,
        ])->assertOk()->assertJsonPath('success', true);
    }

    public function test_resetting_to_the_current_password_is_refused(): void
    {
        $code = $this->requestCode();
        $this->verifyCode($code);

        $this->reset($code, self::OLD_PASSWORD)
            ->assertStatus(422)
            ->assertJsonPath('reason', 'same_password');

        $this->assertTrue(Hash::check(self::OLD_PASSWORD, $this->user->fresh()->password));

        // The code was not burned by the rejection, so a real change still works.
        $this->reset($code)->assertOk();
    }

    public function test_email_lookup_is_case_insensitive(): void
    {
        $this->user->update(['email' => 'Mixed.Case@Homify.test']);

        $code = $this->requestCode('mixed.case@homify.test');
        $this->verifyCode($code, 'MIXED.CASE@homify.test');
        $this->reset($code, self::NEW_PASSWORD, 'Mixed.Case@HOMIFY.TEST')->assertOk();

        $this->postJson('/api/v1/login', [
            'email' => 'mixed.case@homify.test',
            'password' => self::NEW_PASSWORD,
        ])->assertOk();
    }

    public function test_reset_codes_are_stored_hashed(): void
    {
        $code = $this->requestCode();

        $record = OtpVerification::where('email', self::EMAIL)
            ->where('type', 'password_reset')
            ->latest('id')
            ->firstOrFail();

        $this->assertNotSame($code, $record->otp);
        $this->assertSame(64, strlen($record->otp), 'Stored value should be a sha256 HMAC');
        $this->assertTrue($record->matchesCode($code));
        $this->assertFalse($record->matchesCode('000000'));
    }

    public function test_the_owner_is_told_when_the_password_changes(): void
    {
        $code = $this->requestCode();
        $this->verifyCode($code);
        $this->reset($code)->assertOk();

        Mail::assertSent(PasswordChangedMail::class, function (PasswordChangedMail $mail) {
            return $mail->hasTo(self::EMAIL);
        });
    }
}
