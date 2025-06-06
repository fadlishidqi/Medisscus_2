<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class CheckRole
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next, ...$roles): Response
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

        // Check if user has any of the required roles
        if (!in_array($user->role, $roles)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Access denied. Insufficient privileges.',
                'error_code' => 'INSUFFICIENT_PRIVILEGES',
                'data' => [
                    'user_role' => $user->role,
                    'required_roles' => $roles
                ]
            ], 403);
        }

        return $next($request);
    }
}