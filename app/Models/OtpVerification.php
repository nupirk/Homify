<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OtpVerification extends Model
{
    protected $fillable = [
        'phone',
        'email',
        'channel',
        'otp',
        'type',
        'expires_at',
        'is_verified',
        'verified_at',
        'consumed_at',
        'attempts',
        'ip_address',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'verified_at' => 'datetime',
        'consumed_at' => 'datetime',
        'is_verified' => 'boolean',
        'attempts' => 'integer',
    ];

    /**
     * The code exactly as generated, held in memory only.
     *
     * The `otp` column stores an HMAC, so the plaintext exists just long
     * enough to be delivered (mail/SMS response) and is never written down.
     * It is null on any record loaded back from the database.
     */
    public ?string $plainOtp = null;

    /** Keyed hash of a code. A leaked database alone cannot recover codes. */
    public static function hashCode(string $code): string
    {
        return hash_hmac('sha256', $code, (string) config('app.key'));
    }

    /** Timing-safe comparison against the stored hash. */
    public function matchesCode(string $code): bool
    {
        return hash_equals((string) $this->otp, self::hashCode($code));
    }

    /**
     * Check if OTP is expired
     */
    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    /**
     * Check if OTP has exceeded max attempts
     */
    public function hasExceededAttempts(): bool
    {
        return $this->attempts >= 5;
    }

    /**
     * Increment attempt count
     */
    public function incrementAttempts(): void
    {
        $this->increment('attempts');
    }

    /**
     * Mark as verified
     */
    public function markAsVerified(): void
    {
        $this->update([
            'is_verified' => true,
            'verified_at' => now(),
        ]);
    }

    /**
     * Mark as consumed so this code can never be used again
     * (e.g. after it has reset a password).
     */
    public function markAsConsumed(): void
    {
        $this->update([
            'consumed_at' => now(),
        ]);
    }

    /**
     * Consume every outstanding password_reset code for an identifier.
     *
     * Only the code used for a reset used to be consumed, so an older code
     * that had already been verified stayed usable for the rest of its
     * 10-minute window - a second reset from an email the user thought was
     * spent.
     */
    public static function consumeAllForReset(string $channel, string $identifier): int
    {
        return self::where($channel === 'email' ? 'email' : 'phone', $identifier)
            ->where('type', 'password_reset')
            ->whereNull('consumed_at')
            ->update(['consumed_at' => now()]);
    }

    /**
     * Retire older reset codes once a newer one is verified, so only the most
     * recently proven code can reset the password.
     */
    public static function invalidateOtherResetCodes(string $channel, string $identifier, int $keepId): void
    {
        self::where($channel === 'email' ? 'email' : 'phone', $identifier)
            ->where('type', 'password_reset')
            ->where('id', '!=', $keepId)
            ->whereNull('consumed_at')
            ->update(['consumed_at' => now()]);
    }

    /**
     * Find the latest verified, unconsumed, unexpired password_reset OTP
     * for a phone number. Returns null when there is nothing to consume -
     * callers must not distinguish "unknown phone" from "no valid OTP".
     */
    public static function findVerifiedForReset(string $phone): ?self
    {
        return self::where('phone', $phone)
            ->where('type', 'password_reset')
            ->where('is_verified', true)
            ->whereNull('consumed_at')
            ->where('expires_at', '>', now())
            ->latest()
            ->first();
    }

    /**
     * Email-channel twin of findVerifiedForReset(). Same contract: a null
     * result must be indistinguishable from "unknown email" at the call site.
     */
    public static function findVerifiedForResetEmail(string $email): ?self
    {
        return self::where('email', $email)
            ->where('type', 'password_reset')
            ->where('is_verified', true)
            ->whereNull('consumed_at')
            ->where('expires_at', '>', now())
            ->latest()
            ->first();
    }

    /**
     * Generate a new OTP code
     */
    public static function generateOtp(): string
    {
        return str_pad((string) random_int(100000, 999999), 6, '0', STR_PAD_LEFT);
    }

    /**
     * Get the latest unverified OTP for a phone number and type
     */
    public static function getLatestForPhone(string $phone, string $type = 'registration'): ?self
    {
        return self::where('phone', $phone)
            ->where('type', $type)
            ->where('is_verified', false)
            ->where('expires_at', '>', now())
            ->latest()
            ->first();
    }

    /**
     * Get the latest unverified OTP for an email address and type
     */
    public static function getLatestForEmail(string $email, string $type = 'registration'): ?self
    {
        return self::where('email', $email)
            ->where('type', $type)
            ->where('is_verified', false)
            ->where('expires_at', '>', now())
            ->latest()
            ->first();
    }

    /**
     * Create a new OTP for a phone number
     */
    public static function createForPhone(string $phone, string $type = 'registration', ?string $ipAddress = null): self
    {
        // Delete old unverified OTPs for this phone
        self::where('phone', $phone)
            ->where('type', $type)
            ->where('is_verified', false)
            ->delete();

        return self::issue(['phone' => $phone, 'channel' => 'phone'], $type, $ipAddress);
    }

    /**
     * Create a new OTP for an email address.
     *
     * Used where SMS is unavailable/unwanted (e.g. a zero-budget deployment):
     * the code is delivered by email but the record is otherwise identical.
     */
    public static function createForEmail(string $email, string $type = 'registration', ?string $ipAddress = null): self
    {
        self::where('email', $email)
            ->where('type', $type)
            ->where('is_verified', false)
            ->delete();

        return self::issue(['email' => $email, 'channel' => 'email'], $type, $ipAddress);
    }

    /**
     * @param  array{phone?: string, email?: string, channel: string}  $identifier
     */
    private static function issue(array $identifier, string $type, ?string $ipAddress): self
    {
        $plain = self::generateOtp();

        $record = self::create(array_merge($identifier, [
            'otp' => self::hashCode($plain),
            'type' => $type,
            'expires_at' => now()->addMinutes(10),
            'ip_address' => $ipAddress,
        ]));

        $record->plainOtp = $plain;

        return $record;
    }
}
