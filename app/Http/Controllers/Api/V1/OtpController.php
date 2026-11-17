<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\OtpVerification;
use App\Models\User;
use App\Services\EmailOtpService;
use App\Services\SmsService;
use App\Support\Redactor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;

/**
 * OTP send / verify / resend for both delivery channels.
 *
 * `channel` selects the identifier and the transport:
 *   - phone (default) -> SMS via SmsService
 *   - email           -> mail via EmailOtpService (free, no SMS spend)
 *
 * The phone channel's request/response shape is frozen: existing clients and
 * tests depend on `data` being exactly [phone, expires_at, ...].
 */
class OtpController extends Controller
{
    protected SmsService $smsService;
    protected EmailOtpService $emailOtpService;

    public function __construct(SmsService $smsService, EmailOtpService $emailOtpService)
    {
        $this->smsService = $smsService;
        $this->emailOtpService = $emailOtpService;
    }

    /**
     * Send an OTP to a phone number or email address.
     */
    public function sendOtp(Request $request): JsonResponse
    {
        $request->validate([
            'channel' => 'nullable|in:phone,email',
            'phone' => 'required_unless:channel,email|nullable|string',
            'email' => 'required_if:channel,email|nullable|email',
            'type' => 'nullable|in:registration,login,password_reset',
        ]);

        $channel = $request->input('channel', 'phone');
        $type = $request->input('type', 'registration');
        $identifier = $channel === 'email' ? $request->input('email') : $request->input('phone');

        // Rate limiting: 3 requests per 10 minutes per identifier.
        $key = 'otp-send:' . $channel . ':' . $identifier;
        if (RateLimiter::tooManyAttempts($key, 3)) {
            $seconds = RateLimiter::availableIn($key);
            return response()->json([
                'success' => false,
                'message' => "Too many OTP requests. Please try again in " . ceil($seconds / 60) . " minutes."
            ], 429);
        }

        try {
            // For registration, check the identifier is not already verified.
            if ($type === 'registration' && $this->hasVerifiedUser($channel, $identifier)) {
                return response()->json([
                    'success' => false,
                    'message' => $channel === 'email'
                        ? 'Email address is already registered and verified'
                        : 'Phone number already registered and verified'
                ], 400);
            }

            // Password recovery must not reveal which accounts exist: an
            // unknown identifier gets the exact same success payload as a real
            // request, but no OTP record is created and nothing is delivered.
            if ($type === 'password_reset' && !$this->userExists($channel, $identifier)) {
                RateLimiter::hit($key, 600); // 10 minutes

                $maskedExpiresAt = now()->addMinutes(10);

                Log::info('Password reset requested for unknown identifier - delivery suppressed', [
                    'channel' => $channel,
                    'identifier' => $this->mask($channel, $identifier),
                ]);

                return response()->json([
                    'success' => true,
                    'message' => 'OTP sent successfully',
                    'data' => [
                        $channel => $identifier,
                        'expires_at' => $maskedExpiresAt,
                        'expires_in_seconds' => $maskedExpiresAt->diffInSeconds(now()),
                        // Same dev-only shape as a real send; the code is not
                        // stored anywhere, so verification will never match it.
                        'otp' => config('app.expose_dev_otp') ? OtpVerification::generateOtp() : null,
                    ]
                ]);
            }

            // Create OTP record on the matching channel.
            $otpRecord = $channel === 'email'
                ? OtpVerification::createForEmail($identifier, $type, $request->ip())
                : OtpVerification::createForPhone($identifier, $type, $request->ip());

            // Deliver on the matching transport.
            $sent = $channel === 'email'
                ? $this->emailOtpService->sendOtp($identifier, $otpRecord->otp)
                : $this->smsService->sendOtp($identifier, $otpRecord->otp);

            if (!$sent) {
                Log::warning('OTP delivery failed', [
                    'channel' => $channel,
                    'identifier' => $this->mask($channel, $identifier),
                    'type' => $type,
                ]);
                // Don't fail the request, just log it.
            }

            // Increment rate limiter
            RateLimiter::hit($key, 600); // 10 minutes

            Log::info('OTP sent', [
                'channel' => $channel,
                'identifier' => $this->mask($channel, $identifier),
                'type' => $type,
                'expires_at' => $otpRecord->expires_at
            ]);

            return response()->json([
                'success' => true,
                'message' => 'OTP sent successfully',
                'data' => [
                    $channel => $identifier,
                    'expires_at' => $otpRecord->expires_at,
                    'expires_in_seconds' => $otpRecord->expires_at->diffInSeconds(now()),
                    // For development only - opt in with EXPOSE_DEV_OTP=true.
                    'otp' => config('app.expose_dev_otp') ? $otpRecord->otp : null,
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('OTP send failed', [
                'channel' => $channel,
                'identifier' => $this->mask($channel, $identifier),
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to send OTP. Please try again.'
            ], 500);
        }
    }

    /**
     * Verify an OTP for the given channel.
     */
    public function verifyOtp(Request $request): JsonResponse
    {
        $request->validate([
            'channel' => 'nullable|in:phone,email',
            'phone' => 'required_unless:channel,email|nullable|string',
            'email' => 'required_if:channel,email|nullable|email',
            'otp' => 'required|string|size:6',
            'type' => 'nullable|in:registration,login,password_reset',
        ]);

        $channel = $request->input('channel', 'phone');
        $otp = $request->input('otp');
        $type = $request->input('type', 'registration');
        $identifier = $channel === 'email' ? $request->input('email') : $request->input('phone');

        // Rate limiting: 5 verification attempts per 10 minutes per identifier.
        $key = 'otp-verify:' . $channel . ':' . $identifier;
        if (RateLimiter::tooManyAttempts($key, 5)) {
            $seconds = RateLimiter::availableIn($key);
            return response()->json([
                'success' => false,
                'message' => "Too many verification attempts. Please try again in " . ceil($seconds / 60) . " minutes."
            ], 429);
        }

        try {
            $otpRecord = $channel === 'email'
                ? OtpVerification::getLatestForEmail($identifier, $type)
                : OtpVerification::getLatestForPhone($identifier, $type);

            if (!$otpRecord) {
                RateLimiter::hit($key, 600);
                return response()->json([
                    'success' => false,
                    'message' => 'No valid OTP found. Please request a new one.'
                ], 400);
            }

            // Check if expired
            if ($otpRecord->isExpired()) {
                return response()->json([
                    'success' => false,
                    'message' => 'OTP has expired. Please request a new one.'
                ], 400);
            }

            // Check if too many attempts
            if ($otpRecord->hasExceededAttempts()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Too many failed attempts. Please request a new OTP.'
                ], 400);
            }

            // Verify OTP
            if ($otpRecord->otp !== $otp) {
                $otpRecord->incrementAttempts();
                RateLimiter::hit($key, 600);

                $remainingAttempts = 5 - $otpRecord->attempts;
                return response()->json([
                    'success' => false,
                    'message' => "Invalid OTP. {$remainingAttempts} attempts remaining."
                ], 400);
            }

            // OTP is valid - mark as verified
            $otpRecord->markAsVerified();

            // Stamp the matching verification timestamp for registration.
            if ($type === 'registration') {
                $this->markUserVerified($channel, $identifier);
            }

            // Clear rate limiters
            RateLimiter::clear($key);
            RateLimiter::clear('otp-send:' . $channel . ':' . $identifier);

            Log::info('OTP verified successfully', [
                'channel' => $channel,
                'identifier' => $this->mask($channel, $identifier),
                'type' => $type
            ]);

            return response()->json([
                'success' => true,
                'message' => $channel === 'email'
                    ? 'Email verified successfully'
                    : 'Phone number verified successfully',
                'data' => [
                    $channel => $identifier,
                    'verified_at' => now()
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('OTP verification failed', [
                'channel' => $channel,
                'identifier' => $this->mask($channel, $identifier),
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Verification failed. Please try again.'
            ], 500);
        }
    }

    /**
     * Resend an OTP (same channel rules as sendOtp, plus a 30s cooldown).
     */
    public function resendOtp(Request $request): JsonResponse
    {
        $request->validate([
            'channel' => 'nullable|in:phone,email',
            'phone' => 'required_unless:channel,email|nullable|string',
            'email' => 'required_if:channel,email|nullable|email',
            'type' => 'nullable|in:registration,login,password_reset',
        ]);

        $channel = $request->input('channel', 'phone');
        $type = $request->input('type', 'registration');
        $identifier = $channel === 'email' ? $request->input('email') : $request->input('phone');

        // Check if there's a recent OTP (less than 30 seconds ago).
        $latestOtp = OtpVerification::where($channel === 'email' ? 'email' : 'phone', $identifier)
            ->where('type', $type)
            ->latest()
            ->first();

        if ($latestOtp && $latestOtp->created_at->gt(now()->subSeconds(30))) {
            $elapsed = $latestOtp->created_at->diffInSeconds(now());
            $retryAfter = max(1, 30 - $elapsed);

            return response()->json([
                'success' => false,
                'message' => "Please wait {$retryAfter} seconds before requesting a new OTP",
                'retry_after_seconds' => $retryAfter,
            ], 429);
        }

        // Use the same sendOtp logic.
        return $this->sendOtp($request);
    }

    private function hasVerifiedUser(string $channel, string $identifier): bool
    {
        return $channel === 'email'
            ? User::where('email', $identifier)->whereNotNull('email_verified_at')->exists()
            : User::where('phone', $identifier)->whereNotNull('phone_verified_at')->exists();
    }

    private function userExists(string $channel, string $identifier): bool
    {
        return $channel === 'email'
            ? User::where('email', $identifier)->exists()
            : User::where('phone', $identifier)->exists();
    }

    private function markUserVerified(string $channel, string $identifier): void
    {
        if ($channel === 'email') {
            $user = User::where('email', $identifier)->first();
            if ($user && !$user->email_verified_at) {
                $user->update(['email_verified_at' => now()]);
            }
            return;
        }

        $user = User::where('phone', $identifier)->first();
        if ($user && !$user->phone_verified_at) {
            $user->update(['phone_verified_at' => now()]);
        }
    }

    private function mask(string $channel, ?string $identifier): string
    {
        return $channel === 'email'
            ? Redactor::email($identifier)
            : Redactor::phone($identifier);
    }
}
