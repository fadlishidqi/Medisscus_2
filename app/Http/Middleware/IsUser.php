<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class IsUser
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Check if user is authenticated
        if (!Auth::check()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Authentication required',
                'error_code' => 'UNAUTHENTICATED'
            ], 401);
        }

        $user = Auth::user();

        // Check if user is regular user (not admin)
        if (!$user->isUser()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Access denied. User role required.',
                'error_code' => 'INSUFFICIENT_PRIVILEGES',
                'data' => [
                    'user_role' => $user->role,
                    'required_role' => 'user'
                ]
            ], 403);
        }

        return $next($request);
    }
}