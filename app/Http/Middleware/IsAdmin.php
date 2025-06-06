<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class IsAdmin
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

        // Check if user is admin
        if (!$user->isAdmin()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Access denied. Admin role required.',
                'error_code' => 'INSUFFICIENT_PRIVILEGES',
                'data' => [
                    'user_role' => $user->role,
                    'required_role' => 'admin'
                ]
            ], 403);
        }

        return $next($request);
    }
}