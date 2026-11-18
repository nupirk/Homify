<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\AuditService;
use App\Support\EmailAddress;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class AuthController extends Controller
{
    /**
     * Show the admin login form
     */
    public function showLoginForm()
    {
        // If already logged in as admin, redirect to dashboard
        if (Auth::check() && Auth::user()->user_type === 'admin') {
            return redirect()->route('admin.dashboard');
        }

        return view('admin.auth.login');
    }

    /**
     * Handle admin login
     *
     * Every attempt - successful or not - is written to the Laravel log with
     * the email, IP and user agent, and to the audit trail so it is visible in
     * the admin dashboard. The submitted password is never logged.
     */
    public function login(Request $request)
    {
        // Same canonical form as the API: an address typed with different case
        // must find the same admin account.
        $request->merge([
            'email' => EmailAddress::normalize($request->input('email')),
        ]);

        $request->validate([
            'email' => 'required|email',
            'password' => 'required|string|min:6',
        ]);

        $email = (string) $request->input('email');
        $remember = $request->filled('remember');

        if (!Auth::attempt(['email' => $email, 'password' => $request->input('password')], $remember)) {
            $this->recordFailure($request, $email, 'invalid_credentials');

            return back()->withErrors([
                'email' => 'The provided credentials do not match our records.',
            ])->withInput($request->except('password'));
        }

        $user = Auth::user();

        if ($user->user_type !== 'admin') {
            Auth::logout();
            $this->recordFailure($request, $email, 'not_admin', $user->user_id);

            return back()->withErrors([
                'email' => 'You do not have admin access to this system.',
            ])->withInput($request->except('password'));
        }

        if (!$user->is_active) {
            Auth::logout();
            $this->recordFailure($request, $email, 'inactive', $user->user_id);

            return back()->withErrors([
                'email' => 'Your account has been deactivated. Please contact support.',
            ])->withInput($request->except('password'));
        }

        // Stop session fixation before the session carries admin privileges.
        $request->session()->regenerate();

        $context = $this->context($request);

        Log::info('Admin login succeeded', array_merge($context, [
            'admin_id' => $user->user_id,
            'email' => $user->email,
            'remember' => $remember,
        ]));

        AuditService::record(
            $user->user_id,
            'admin_login',
            'admin_auth',
            $user->email,
            null,
            array_merge($context, ['remember' => $remember])
        );

        return redirect()->intended(route('admin.dashboard'))
            ->with('success', 'Welcome back, ' . $user->first_name . '!');
    }

    /**
     * Handle admin logout
     */
    public function logout(Request $request)
    {
        $user = Auth::user();

        if ($user) {
            $context = $this->context($request);

            Log::info('Admin logout', array_merge($context, [
                'admin_id' => $user->user_id,
                'email' => $user->email,
            ]));

            AuditService::record(
                $user->user_id,
                'admin_logout',
                'admin_auth',
                $user->email,
                null,
                $context
            );
        }

        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('admin.login')
            ->with('success', 'You have been logged out successfully.');
    }

    /**
     * Log a rejected attempt.
     *
     * Failed logins are the signal that matters during a brute-force attempt,
     * so they go to the log at warning level (visible at LOG_LEVEL=warning and
     * above) with enough context to identify the source.
     */
    private function recordFailure(Request $request, string $email, string $reason, ?int $userId = null): void
    {
        $context = array_merge($this->context($request), [
            'email' => $email,
            'reason' => $reason,
            'admin_id' => $userId,
        ]);

        Log::warning('Admin login failed', $context);

        AuditService::record(
            $userId,
            'admin_login_failed',
            'admin_auth',
            $email,
            null,
            array_merge($context, ['reason' => $reason])
        );
    }

    /** Request context shared by every login log line. */
    private function context(Request $request): array
    {
        return [
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'at' => now()->toIso8601String(),
        ];
    }
}
