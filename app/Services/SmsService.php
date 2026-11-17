<?php

namespace App\Services;

use App\Support\Redactor;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SmsService
{
    protected $gateway;
    protected $token;
    protected $from;

    public function __construct()
    {
        $this->gateway = config('services.sms.gateway', 'sparrow'); // sparrow, twilio, log
        $this->token = config('services.sms.token');
        $this->from = config('services.sms.from', 'Urban');
    }

    /**
     * Send SMS via configured gateway
     */
    public function send(string $phone, string $message): bool
    {
        try {
            switch ($this->gateway) {
                case 'sparrow':
                    return $this->sendViaSparrow($phone, $message);
                case 'twilio':
                    return $this->sendViaTwilio($phone, $message);
                case 'log':
                default:
                    return $this->sendViaLog($phone, $message);
            }
        } catch (\Exception $e) {
            Log::error('SMS sending failed', [
                'gateway' => $this->gateway,
                'phone' => Redactor::phone($phone),
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Send OTP SMS
     */
    public function sendOtp(string $phone, string $otp): bool
    {
        $message = "Your Urban verification code is: {$otp}. Valid for 10 minutes. Do not share this code.";
        return $this->send($phone, $message);
    }

    /**
     * Send via Sparrow SMS (Nepal)
     */
    protected function sendViaSparrow(string $phone, string $message): bool
    {
        $url = 'http://api.sparrowsms.com/v2/sms';

        $response = Http::asForm()->timeout(10)->post($url, [
            'token' => $this->token,
            'from' => $this->from,
            'to' => $this->formatPhoneForSparrow($phone),
            'text' => $message,
        ]);

        if ($response->successful()) {
            $data = $response->json();
            Log::info('Sparrow SMS sent', [
                'phone' => Redactor::phone($phone),
                'response' => $data
            ]);
            return $data['response_code'] === 200;
        }

        Log::error('Sparrow SMS failed', [
            'phone' => Redactor::phone($phone),
            'status' => $response->status(),
            'body' => $response->body()
        ]);

        return false;
    }

    /**
     * Send via Twilio (International)
     */
    protected function sendViaTwilio(string $phone, string $message): bool
    {
        $accountSid = config('services.sms.twilio_sid');
        $authToken = $this->token;
        $fromNumber = $this->from;

        $url = "https://api.twilio.com/2010-04-01/Accounts/{$accountSid}/Messages.json";

        $response = Http::withBasicAuth($accountSid, $authToken)
            ->asForm()
            ->timeout(10)
            ->post($url, [
                'From' => $fromNumber,
                'To' => $phone,
                'Body' => $message,
            ]);

        if ($response->successful()) {
            Log::info('Twilio SMS sent', [
                'phone' => Redactor::phone($phone),
                'sid' => $response->json('sid')
            ]);
            return true;
        }

        Log::error('Twilio SMS failed', [
            'phone' => Redactor::phone($phone),
            'status' => $response->status(),
            'body' => $response->body()
        ]);

        return false;
    }

    /**
     * Log SMS instead of sending (for development)
     */
    protected function sendViaLog(string $phone, string $message): bool
    {
        // Log mode exists so developers can read OTP codes locally. Even here
        // the message body is deliberately omitted so a stray log file can
        // never leak one-time codes.
        Log::info('SMS (Log Mode)', [
            'phone' => Redactor::phone($phone),
            'message_length' => strlen($message),
        ]);

        // In development, always return true
        return true;
    }

    /**
     * Format phone number for Sparrow SMS (Nepal)
     * Sparrow accepts: 98XXXXXXXX or 977-98XXXXXXXX
     */
    protected function formatPhoneForSparrow(string $phone): string
    {
        // Remove all non-numeric characters
        $phone = preg_replace('/[^0-9]/', '', $phone);

        // If starts with 977 (Nepal country code), remove it
        if (substr($phone, 0, 3) === '977') {
            $phone = substr($phone, 3);
        }

        // If starts with +977, remove it
        if (substr($phone, 0, 4) === '+977') {
            $phone = substr($phone, 4);
        }

        // Should now be 10 digits starting with 98
        return $phone;
    }
}
