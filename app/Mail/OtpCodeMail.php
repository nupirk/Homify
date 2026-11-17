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
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Your Homify verification code',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.otp',
            with: [
                'code' => $this->code,
                'expiresInMinutes' => $this->expiresInMinutes,
            ],
        );
    }
}
