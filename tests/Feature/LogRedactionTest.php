<?php

namespace Tests\Feature;

use App\Models\OtpVerification;
use App\Models\PartnerBalance;
use App\Models\PartnerProfile;
use App\Models\User;
use App\Services\SmsService;
use App\Support\Redactor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Tests\CreatesApplication;
use Tests\TestCase;

/**
 * Log hygiene contract.
 *
 * Log files are read by support staff, shipped to aggregators and retained far
 * longer than the data they describe. Secrets and PII (OTP codes, phone
 * numbers, bearer tokens, bank/wallet identifiers) must never reach them.
 */
class LogRedactionTest extends TestCase
{
    use CreatesApplication, RefreshDatabase;

    /** @var array<int, string> */
    private array $captured = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->captured = [];
        Log::listen(function ($event) {
            $this->captured[] = $event->level . ' ' . $event->message . ' '
                . json_encode($event->context ?? []);
        });
    }

    private function assertNothingLoggedContains(string $needle, string $reason): void
    {
        $haystack = implode("\n", $this->captured);

        $this->assertStringNotContainsString(
            $needle,
            $haystack,
            "Log must never contain {$reason}. Got:\n{$haystack}"
        );
    }

    public function test_registration_otp_is_never_written_to_the_log(): void
    {
        $response = $this->postJson('/api/v1/register', [
            'first_name' => 'Log',
            'last_name' => 'Leak',
            'email' => 'log.leak@urban.test',
            'phone' => '9812345678',
            'password' => 'secretPassword123',
            'password_confirmation' => 'secretPassword123',
            'user_type' => 'customer',
        ]);

        $response->assertSuccessful();

        $otp = OtpVerification::where('email', 'log.leak@urban.test')->latest('id')->first();
        $this->assertNotNull($otp, 'Registration should create an OTP record');

        $this->assertNothingLoggedContains($otp->otp, 'the registration OTP code');
        $this->assertNothingLoggedContains('9812345678', 'the full phone number');
        $this->assertNothingLoggedContains('secretPassword123', 'the account password');
    }

    public function test_sms_log_gateway_never_writes_the_code_or_full_number(): void
    {
        config(['services.sms.gateway' => 'log']);

        $sent = (new SmsService())->sendOtp('9812345678', '424242');
        $this->assertTrue($sent);

        $this->assertNothingLoggedContains('424242', 'an OTP code in log/SMS mode');
        $this->assertNothingLoggedContains('9812345678', 'the full phone number');
    }

    public function test_payout_request_logs_no_bank_details_or_auth_header(): void
    {
        $partner = User::create([
            'first_name' => 'Payout',
            'last_name' => 'Partner',
            'email' => 'payout.partner@urban.test',
            'phone' => '9800002222',
            'password' => Hash::make('secretPassword123'),
            'user_type' => 'partner',
            'is_active' => true,
            'phone_verified_at' => now(),
        ]);

        $profile = new PartnerProfile(['status' => 'active']);
        $profile->partner_id = $partner->user_id;
        $profile->save();

        PartnerBalance::create([
            'partner_id' => $profile->partner_id,
            'available_balance' => 5000,
            'total_earnings' => 5000,
            'total_withdrawn' => 0,
            'last_updated' => now(),
        ]);

        $this->actingAs($partner, 'sanctum');

        $this->postJson('/api/v1/payouts/request', [
            'amount' => 1000,
            'payment_method' => 'bank_transfer',
            'bank_transfer_details' => [
                'account_number' => '999888777666',
                'account_name' => 'Payout Partner Pvt Ltd',
                'bank_name' => 'Nabil Bank',
                'branch' => 'Durbar Marg',
            ],
        ]);

        $this->assertNothingLoggedContains('999888777666', 'the bank account number');
        $this->assertNothingLoggedContains('Payout Partner Pvt Ltd', 'the bank account name');
        $this->assertNothingLoggedContains('Bearer ', 'a bearer token');
        $this->assertNothingLoggedContains('authorization', 'the authorization header');
    }

    public function test_redactor_masks_phones_and_redacts_nested_sensitive_keys(): void
    {
        $masked = Redactor::phone('9812345678');
        $this->assertStringNotContainsString('9812345678', $masked);
        $this->assertStringStartsWith('981', $masked);
        $this->assertStringEndsWith('78', $masked);

        $redacted = Redactor::redactKeys([
            'payment_method' => 'bank_transfer',
            'bank_transfer_details' => [
                'account_number' => '999888777666',
                'bank_name' => 'Nabil Bank',
            ],
        ], Redactor::sensitiveKeys());

        $this->assertSame('[redacted]', $redacted['bank_transfer_details']['account_number']);
        $this->assertSame('[redacted]', $redacted['bank_transfer_details']['bank_name']);
        $this->assertSame('bank_transfer', $redacted['payment_method']);
    }
}
