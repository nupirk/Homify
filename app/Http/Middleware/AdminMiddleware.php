<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AdminMiddleware
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Check if user is authenticated
        if (!$request->user()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized. Please log in.'
            ], 401);
        }

        // Check if user is admin
        if ($request->user()->user_type !== 'admin') {
            return response()->json([
                'success' => false,
                'message' => 'Forbidden. Admin access required.'
            ], 403);
        }

        // Check if admin account is active
        if (!$request->user()->is_active) {
            return response()->json([
                'success' => false,
                'message' => 'Account disabled. Contact support.'
            ], 403);
        }

        return $next($request);
    }
}
