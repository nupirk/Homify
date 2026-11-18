<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * One-time verification code delivered by email.
 *
 * Email is the free OTP channel: it needs no per-message spend and no native
 * rebuild, which keeps phone/SMS budgets optional.
 */
class OtpCodeMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $code,
        public int $expiresInMinutes = 10,
        /** 'verification' for signup, 'password_reset' for recovery. */
        public string $purpose = 'verification',
    ) {
    }

    public function envelope(): Envelope
    {
        // A password reset must not look like routine signup mail - same
        // subject on both makes a real reset easy to ignore (and easy for a
        // phisher to imitate).
        return new Envelope(
            subject: $this->purpose === 'password_reset'
                ? 'Reset your Homify password'
                : 'Your Homify verification code',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.otp',
            with: [
                'code' => $this->code,
                'expiresInMinutes' => $this->expiresInMinutes,
                'purpose' => $this->purpose,
            ],
        );
    }
}
