<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\LoginRequest;
use App\Http\Requests\RegisterRequest;
use App\Mail\PasswordChangedMail;
use App\Models\User;
use App\Models\OtpVerification;
use App\Services\EmailOtpService;
use App\Support\EmailAddress;
use App\Support\Redactor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class AuthController extends Controller
{
    public function register(RegisterRequest $request): JsonResponse
    {
        try {
            $validated = $request->validated();

            // Create the user
            $user = User::create([
                'first_name' => $validated['first_name'],
                'last_name' => $validated['last_name'],
                'email' => $validated['email'],
                'phone' => $validated['phone'],
                'user_type' => $validated['user_type'],
                'password' => Hash::make($validated['password']),
                'is_active' => true,
            ]);

            // Create partner profile if user type is partner
            if ($validated['user_type'] === 'partner') {
                \App\Models\PartnerProfile::create([
                    'partner_id' => $user->user_id,
                    'status' => 'onboarding',
                    'rating_avg' => null,
                    'total_ratings' => 0,
                    'bank_account' => null,
                    'kyc_verified_at' => null
                ]);
            }

            // No session is issued at registration: the account exists but
            // stays unusable until the emailed code is verified. This is what
            // stops the OTP step from being skippable - there is no token to
            // fall back on.
            $devOtp = null;
            $otpSent = false;
            try {
                $emailOtpService = app(EmailOtpService::class);
                $otpRecord = OtpVerification::createForEmail(
                    $user->email,
                    'registration',
                    $request->ip()
                );
                $otpSent = $emailOtpService->sendOtp($user->email, $otpRecord->plainOtp ?? '', 'registration');

                $devOtp = $otpRecord->plainOtp;

                // Never log the code - the log file is not a secure channel.
                Log::info('Registration OTP sent', [
                    'user_id' => $user->user_id,
                    'email' => Redactor::email($user->email),
                ]);
            } catch (\Exception $e) {
                Log::error('Failed to send registration OTP', [
                    'user_id' => $user->user_id,
                    'error' => $e->getMessage()
                ]);
                // Don't fail registration if OTP fails - user can request resend
            }

            return response()->json([
                'success' => true,
                'message' => $otpSent
                    ? 'Registration successful. Please verify your email address.'
                    : 'Registration successful, but we could not send the verification email. Please request a new code.',
                'requires_verification' => true,
                'otp_sent' => $otpSent,
                'user' => [
                    'id' => $user->user_id,
                    'first_name' => $user->first_name,
                    'last_name' => $user->last_name,
                    'email' => $user->email,
                    'phone' => $user->phone,
                    'user_type' => $user->user_type,
                    'is_active' => $user->is_active,
                    'email_verified_at' => $user->email_verified_at,
                    'phone_verified_at' => $user->phone_verified_at,
                ],
                'token' => null,
                // Development aid only: opt in with EXPOSE_DEV_OTP=true.
                // Never enabled by default, regardless of APP_ENV.
                'dev_otp' => config('app.expose_dev_otp') ? $devOtp : null,
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Registration failed',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function login(LoginRequest $request): JsonResponse
    {
        try {
            $loginField = trim((string) $request->email);

            // Determine if login field is email or phone
            $fieldType = filter_var($loginField, FILTER_VALIDATE_EMAIL) ? 'email' : 'phone';

            // Case-insensitive on the email branch: accounts saved before
            // normalization may hold mixed case, and the user should not have
            // to remember which one they typed at signup.
            $user = $fieldType === 'email'
                ? User::whereRaw('LOWER(email) = ?', [EmailAddress::normalize($loginField)])->first()
                : User::where('phone', $loginField)->first();

            if (!$user || !Hash::check($request->password, $user->password)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid credentials'
                ], 401);
            }

            if (!$user->is_active) {
                return response()->json([
                    'success' => false,
                    'message' => 'Account is deactivated. Please contact support.'
                ], 403);
            }

            // Email verification is a hard gate on every session. The client
            // uses `requires_verification` + `email` to open the OTP sheet and
            // send a fresh code instead of showing "invalid credentials".
            if (!$user->email_verified_at) {
                return response()->json([
                    'success' => false,
                    'message' => 'Please verify your email address before signing in.',
                    'requires_verification' => true,
                    'email' => $user->email,
                ], 403);
            }

            // Update last login
            $user->update(['last_login_at' => now()]);

            $token = $user->createToken('app')->plainTextToken;

            return response()->json([
                'success' => true,
                'message' => 'Login successful',
                'user' => [
                    'id' => $user->user_id,
                    'first_name' => $user->first_name,
                    'last_name' => $user->last_name,
                    'email' => $user->email,
                    'phone' => $user->phone,
                    'user_type' => $user->user_type,
                    'is_active' => $user->is_active,
                    'email_verified_at' => $user->email_verified_at,
                    'phone_verified_at' => $user->phone_verified_at,
                ],
                'token' => $token,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Login failed',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Reset a password using a verified password_reset OTP.
     *
     * The identifier may be a phone number or an email address (the channel
     * that received the code).
     *
     * Anti-enumeration: an unknown identifier, an identifier without a verified
     * OTP, an expired OTP and an already-used OTP all receive the exact same
     * 400 response, so this endpoint cannot be used to probe which accounts
     * exist.
     */
    public function resetPassword(Request $request): JsonResponse
    {
        $request->validate([
            'phone' => 'nullable|string|max:20|required_without:email',
            'email' => 'nullable|email|required_without:phone',
            'otp' => 'required|string|size:6',
            'password' => 'required|string|min:8|confirmed',
        ], [
            'password.min' => 'Password must be at least 8 characters long.',
            'password.confirmed' => 'Password confirmation does not match.',
            'otp.size' => 'The OTP must be 6 digits.',
        ]);

        try {
            $submittedOtp = (string) $request->input('otp');
            $viaEmail = $request->filled('email');

            if ($viaEmail) {
                $identifier = EmailAddress::normalize($request->input('email'));
                $user = User::whereRaw('LOWER(email) = ?', [$identifier])->first();
                $otpRecord = OtpVerification::findVerifiedForResetEmail($identifier);
            } else {
                $identifier = (string) $request->input('phone');
                $user = User::where('phone', $identifier)->first();
                $otpRecord = OtpVerification::findVerifiedForReset($identifier);
            }

            // Single generic failure for every "no valid OTP" case: unknown
            // identifier, never verified, expired, already consumed, or mismatch.
            if (!$user
                || !$otpRecord
                || !$otpRecord->matchesCode($submittedOtp)
            ) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid or expired verification code. Please request a new one.'
                ], 400);
            }

            $newPassword = (string) $request->input('password');

            // Re-submitting the current password means nothing was recovered,
            // and it leaves the account on credentials that may be known to
            // whoever prompted the reset.
            if (Hash::check($newPassword, $user->password)) {
                return response()->json([
                    'success' => false,
                    'message' => 'The new password must be different from your current password.',
                    'reason' => 'same_password',
                ], 422);
            }

            $updates = ['password' => Hash::make($newPassword)];

            // Redeeming a code sent to the mailbox proves control of it, so an
            // account that never verified its email is verified by this act.
            // Otherwise the login gate would refuse the account the user just
            // recovered and demand a second code.
            if ($viaEmail && !$user->email_verified_at) {
                $updates['email_verified_at'] = now();
            }

            $user->update($updates);

            // Every existing session dies with the old password: a stolen
            // token must not survive the reset meant to evict it.
            $user->tokens()->delete();

            // Retire every outstanding reset code for this identifier, not
            // just the one used, so no older code can reset again.
            OtpVerification::consumeAllForReset($viaEmail ? 'email' : 'phone', $identifier);

            // Best-effort: the reset already happened, so a mail failure is
            // only a missing warning, never a failed reset.
            $this->notifyPasswordChanged($user);

            Log::info('Password reset completed', [
                'user_id' => $user->user_id,
                'channel' => $viaEmail ? 'email' : 'phone',
                'sessions_revoked' => true,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Password has been reset successfully'
            ]);
        } catch (\Exception $e) {
            Log::error('Password reset failed', [
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to reset password. Please try again.'
            ], 500);
        }
    }

    /**
     * Tell the account owner their password changed - the only signal a
     * victim gets when someone else resets it. Never throws.
     */
    private function notifyPasswordChanged(User $user): void
    {
        try {
            Mail::to($user->email)->send(new PasswordChangedMail(
                now()->timezone(config('app.timezone'))->format('d M Y, H:i')
            ));
        } catch (\Throwable $e) {
            Log::error('Password changed notice not delivered', [
                'user_id' => $user->user_id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    public function logout(Request $request): JsonResponse
    {
        try {
            $user = $request->user();

            if ($user) {
                // Delete the current access token
                $user->tokens()->where('id', $user->currentAccessToken()->id)->delete();

                return response()->json([
                    'success' => true,
                    'message' => 'Logged out successfully'
                ]);
            }

            return response()->json([
                'success' => false,
                'message' => 'User not authenticated'
            ], 401);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Logout failed',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function user(Request $request): JsonResponse
    {
        try {
            $user = $request->user();

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not authenticated'
                ], 401);
            }

            // Tokens minted before verification was enforced must not restore
            // a session either: the app calls this endpoint on boot.
            if (!$user->email_verified_at) {
                return response()->json([
                    'success' => false,
                    'message' => 'Please verify your email address.',
                    'requires_verification' => true,
                    'email' => $user->email,
                ], 403);
            }

            return response()->json([
                'success' => true,
                'user' => [
                    'id' => $user->id,
                    'first_name' => $user->first_name,
                    'last_name' => $user->last_name,
                    'email' => $user->email,
                    'phone' => $user->phone,
                    'user_type' => $user->user_type,
                    'is_active' => $user->is_active,
                    'email_verified_at' => $user->email_verified_at,
                    'avatar_url' => $user->avatar_url,
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get user data',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
