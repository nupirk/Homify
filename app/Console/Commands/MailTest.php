<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

/**
 * One-shot check that the configured mailer can actually deliver.
 *
 * Run this on the server after touching MAIL_* settings (and after
 * `php artisan config:cache`):
 *
 *     php artisan mail:test you@example.com
 *
 * It prints the effective mailer/host/from, sends a real message and reports
 * the transport error when there is one - which is what the OTP endpoint used
 * to hide behind a "success" response.
 */
class MailTest extends Command
{
    protected $signature = 'mail:test {email : Recipient address for the test message}';

    protected $description = 'Send a test email with the configured mailer and report the result';

    public function handle(): int
    {
        $mailer = (string) config('mail.default');
        $from = (string) config('mail.from.address');

        $this->line('mailer:  ' . $mailer);
        $this->line('host:    ' . (string) config("mail.mailers.{$mailer}.host", '-'));
        $this->line('port:    ' . (string) config("mail.mailers.{$mailer}.port", '-'));
        $this->line('scheme:  ' . (string) config("mail.mailers.{$mailer}.scheme", '-'));
        $this->line('from:    ' . ($from !== '' ? $from : '-'));

        if ($mailer === 'log') {
            $this->warn('MAIL_MAILER=log writes to storage/logs/laravel.log and sends nothing.');
        }

        $to = (string) $this->argument('email');

        try {
            Mail::raw(
                'This is a Homify mail test. If you are reading it, OTP email delivery works.',
                fn ($message) => $message->to($to)->subject('Homify mail test'),
            );
        } catch (\Throwable $e) {
            $this->error('FAILED: ' . $e->getMessage());

            return self::FAILURE;
        }

        $this->info("Sent a test message to {$to}. Check the inbox (and spam) for it.");

        return self::SUCCESS;
    }
}
