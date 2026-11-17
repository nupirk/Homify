<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use App\Models\User;

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
     */
    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required|string|min:6',
        ]);

        $credentials = $request->only('email', 'password');

        // Attempt to authenticate
        if (Auth::attempt($credentials, $request->filled('remember'))) {
            $user = Auth::user();

            // Check if user is admin
            if ($user->user_type === 'admin') {
                // Check if account is active
                if ($user->is_active) {
                    $request->session()->regenerate();

                    return redirect()->intended(route('admin.dashboard'))
                        ->with('success', 'Welcome back, ' . $user->first_name . '!');
                } else {
                    Auth::logout();
                    return back()->withErrors([
                        'email' => 'Your account has been deactivated. Please contact support.',
                    ])->withInput($request->except('password'));
                }
            } else {
                Auth::logout();
                return back()->withErrors([
                    'email' => 'You do not have admin access to this system.',
                ])->withInput($request->except('password'));
            }
        }

        return back()->withErrors([
            'email' => 'The provided credentials do not match our records.',
        ])->withInput($request->except('password'));
    }

    /**
     * Handle admin logout
     */
    public function logout(Request $request)
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('admin.login')
            ->with('success', 'You have been logged out successfully.');
    }
}
