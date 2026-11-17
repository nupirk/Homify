<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\LoginRequest;
use App\Http\Requests\RegisterRequest;
use App\Models\User;
use App\Models\OtpVerification;
use App\Services\EmailOtpService;
use App\Support\Redactor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

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

            $token = $user->createToken('app')->plainTextToken;

            // Send the verification OTP by email. Email is the free channel -
            // it needs no per-message spend and no SMS gateway configured, so a
            // zero-budget deployment can still verify new accounts.
            $devOtp = null;
            try {
                $emailOtpService = app(EmailOtpService::class);
                $otpRecord = OtpVerification::createForEmail(
                    $user->email,
                    'registration',
                    $request->ip()
                );
                $emailOtpService->sendOtp($user->email, $otpRecord->otp);

                $devOtp = $otpRecord->otp;

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
                'message' => 'Registration successful. Please verify your email address.',
                'requires_verification' => true,
                'user' => [
                    'id' => $user->user_id,
                    'first_name' => $user->first_name,
                    'last_name' => $user->last_name,
                    'email' => $user->email,
                    'phone' => $user->phone,
                    'user_type' => $user->user_type,
                    'is_active' => $user->is_active,
                    'phone_verified_at' => $user->phone_verified_at,
                ],
                'token' => $token,
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
            $loginField = $request->email;

            // Determine if login field is email or phone
            $fieldType = filter_var($loginField, FILTER_VALIDATE_EMAIL) ? 'email' : 'phone';

            $user = User::where($fieldType, $loginField)->first();

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
            $submittedOtp = $request->input('otp');
            $email = $request->input('email');
            $phone = $request->input('phone');

            if ($request->filled('email')) {
                $user = User::where('email', $email)->first();
                $otpRecord = OtpVerification::findVerifiedForResetEmail($email);
            } else {
                $user = User::where('phone', $phone)->first();
                $otpRecord = OtpVerification::findVerifiedForReset($phone);
            }

            // Single generic failure for every "no valid OTP" case: unknown
            // identifier, never verified, expired, already consumed, or mismatch.
            if (!$user
                || !$otpRecord
                || !hash_equals((string) $otpRecord->otp, (string) $submittedOtp)
            ) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid or expired verification code. Please request a new one.'
                ], 400);
            }

            // Same hashing path as register()/login().
            $user->update(['password' => Hash::make($request->input('password'))]);

            // Consume the OTP so the same code can never reset twice.
            $otpRecord->markAsConsumed();

            Log::info('Password reset completed', [
                'user_id' => $user->user_id
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
