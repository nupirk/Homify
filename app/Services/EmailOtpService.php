<?php

namespace App\Services;

use App\Mail\OtpCodeMail;
use App\Support\Redactor;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Delivers OTP codes by email - the zero-cost channel.
 *
 * Uses whatever mailer the app is configured with (see MAIL_MAILER). On the
 * Resend free tier this is an SMTP relay, so no extra PHP package is required.
 */
class EmailOtpService
{
    /**
     * Send an OTP to an email address. Never throws: the caller decides
     * whether a failed delivery should fail the request.
     */
    public function sendOtp(string $email, string $otp, string $type = 'registration'): bool
    {
        $mailer = (string) config('mail.default');

        // The `log` driver writes the message to storage/logs and never opens a
        // mail connection. It is the default when MAIL_MAILER is unset or when a
        // cached config predates the SMTP keys, so treat it as a failed send
        // instead of reporting a delivery that never left the server.
        if ($mailer === 'log') {
            Log::error('Email OTP not delivered: MAIL_MAILER=log only writes to the log file', [
                'email' => Redactor::email($email),
                'mailer' => $mailer,
            ]);

            return false;
        }

        try {
            Mail::to($email)->send(new OtpCodeMail(
                $otp,
                purpose: $type === 'password_reset' ? 'password_reset' : 'verification',
            ));

            // The code itself is deliberately never logged - a leaked log plus
            // a leaked inbox would hand over live credentials.
            Log::info('Email OTP sent', [
                'email' => Redactor::email($email),
                'mailer' => $mailer,
                'mail_host' => config("mail.mailers.{$mailer}.host"),
                'mail_from' => config('mail.from.address'),
            ]);

            return true;
        } catch (\Throwable $e) {
            Log::error('Email OTP failed', [
                'email' => Redactor::email($email),
                'mailer' => $mailer,
                'mail_host' => config("mail.mailers.{$mailer}.host"),
                'mail_from' => config('mail.from.address'),
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }
}
