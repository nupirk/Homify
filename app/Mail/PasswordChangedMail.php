<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Sent after a password reset succeeds.
 *
 * Without it a takeover is silent: the attacker changes the password, the
 * owner keeps using a session that still works, and nothing ever says the
 * credentials changed. This notice is the only early warning they get.
 */
class PasswordChangedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $changedAt,
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Your Homify password was changed',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.password-changed',
            with: [
                'changedAt' => $this->changedAt,
            ],
        );
    }
}
