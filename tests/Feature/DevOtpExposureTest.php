<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\CreatesApplication;
use Tests\TestCase;

/**
 * The registration response must not hand out the phone-verification OTP
 * unless an operator has explicitly opted in.
 *
 * Regression: the response used to expose `dev_otp` whenever APP_ENV was
 * anything other than "production", so a staging or "local" deploy silently
 * disclosed a login code for every new account.
 */
class DevOtpExposureTest extends TestCase
{
    use CreatesApplication, RefreshDatabase;

    private function register(int $seed)
    {
        return $this->postJson('/api/v1/register', [
            'first_name' => 'Otp',
            'last_name' => 'Probe',
            'email' => "otp.probe{$seed}@urban.test",
            'phone' => '98100000' . str_pad((string) $seed, 2, '0', STR_PAD_LEFT),
            'password' => 'secretPassword123',
            'password_confirmation' => 'secretPassword123',
            'user_type' => 'customer',
        ]);
    }

    public function test_otp_is_hidden_in_a_non_production_environment_by_default(): void
    {
        // The old bug fired here: env is not "production", but that is not a
        // reason to disclose a one-time code.
        config(['app.env' => 'local']);
        config(['app.expose_dev_otp' => false]);

        $response = $this->register(1);

        $response->assertCreated();
        $this->assertNull($response->json('dev_otp'));
    }

    public function test_otp_is_hidden_even_in_staging(): void
    {
        config(['app.env' => 'staging']);

        $this->assertNull($this->register(2)->json('dev_otp'));
    }

    public function test_otp_is_returned_only_when_explicitly_enabled(): void
    {
        config(['app.env' => 'local']);
        config(['app.expose_dev_otp' => true]);

        $response = $this->register(3);

        $response->assertCreated();
        $this->assertIsString($response->json('dev_otp'));
        $this->assertMatchesRegularExpression('/^[0-9]{6}$/', $response->json('dev_otp'));
    }
}
