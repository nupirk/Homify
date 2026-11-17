<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class AdminAuthMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Check if user is authenticated
        if (!Auth::check()) {
            // Store the intended URL for redirect after login
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized. Please log in.'
                ], 401);
            }

            return redirect()->route('admin.login')
                ->with('error', 'Please log in to access the admin panel.');
        }

        $user = Auth::user();

        // Check if user is admin
        if ($user->user_type !== 'admin') {
            Auth::logout();

            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Forbidden. Admin access required.'
                ], 403);
            }

            return redirect()->route('admin.login')
                ->with('error', 'You do not have admin access to this system.');
        }

        // Check if admin account is active
        if (!$user->is_active) {
            Auth::logout();

            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Account disabled. Contact support.'
                ], 403);
            }

            return redirect()->route('admin.login')
                ->with('error', 'Your account has been deactivated. Please contact support.');
        }

        return $next($request);
    }
}
