<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use App\Models\User;
use Symfony\Component\HttpFoundation\Response;

class ValidateDevice
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        try {
            $user = Auth::user();
            
            if (!$user) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'User not authenticated',
                    'error_code' => 'UNAUTHENTICATED'
                ], 401);
            }
            
            $currentDeviceId = User::generateDeviceId($request);

            // Check if user is logged in from another device
            if ($user->isLoggedInFromAnotherDevice($currentDeviceId)) {
                Auth::logout();
                
                Log::warning('User attempted login from different device', [
                    'user_id' => $user->id,
                    'current_device' => $currentDeviceId,
                    'registered_device' => $user->device_id,
                    'ip' => $request->ip(),
                    'user_agent' => $request->header('User-Agent')
                ]);

                return response()->json([
                    'status' => 'error',
                    'message' => 'Anda telah login dari device lain. Silakan login ulang.',
                    'error_code' => 'DEVICE_MISMATCH',
                    'data' => [
                        'current_device' => User::getDeviceName($request->header('User-Agent')),
                        'registered_device' => $user->device_name,
                        'last_login' => $user->last_login_at ? $user->last_login_at->format('Y-m-d H:i:s') : null
                    ]
                ], 401);
            }

            return $next($request);

        } catch (\Exception $e) {
            Log::error('Device validation error: ' . $e->getMessage(), [
                'user_id' => Auth::id(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Device validation failed',
                'error_code' => 'DEVICE_VALIDATION_ERROR'
            ], 500);
        }
    }
}