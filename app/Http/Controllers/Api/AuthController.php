<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Requests\Auth\ForgotPasswordRequest;
use App\Http\Requests\Auth\ResetPasswordRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Notifications\ResetPasswordNotification;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Tymon\JWTAuth\Facades\JWTAuth;
use Tymon\JWTAuth\Exceptions\JWTException;

class AuthController extends Controller
{
    /**
     * Register a new user
     */
    public function register(RegisterRequest $request): JsonResponse
    {
        try {
            $user = User::create([
                'name' => $request->name,
                'username' => $request->username,
                'email' => $request->email,
                'university' => $request->university,
                'phone' => $request->phone,
                'role' => 'user',
                'password' => Hash::make($request->password),
            ]);

            event(new Registered($user));

            // Generate device info
            $deviceId = User::generateDeviceId($request);
            $deviceName = User::getDeviceName($request->header('User-Agent', ''));
            
            // Update user device info
            $user->updateDeviceInfo(
                $deviceId,
                $deviceName,
                $request->ip(),
                $request->header('User-Agent', '')
            );

            $token = JWTAuth::fromUser($user);

            return response()->json([
                'status' => 'success',
                'message' => 'Registrasi berhasil',
                'data' => [
                    'user' => new UserResource($user->fresh()),
                    'access_token' => $token,
                    'token_type' => 'bearer',
                    'expires_in' => $this->getJWTExpiresIn(),
                    'expires_at' => now()->addMinutes(config('jwt.ttl'))->toDateTimeString(),
                    'device_info' => [
                        'device_id' => $deviceId,
                        'device_name' => $deviceName,
                        'login_time' => now()->format('Y-m-d H:i:s')
                    ]
                ]
            ], 201)
                ->header('Content-Type', 'application/json');
        } catch (\Exception $e) {
            Log::error('Register error: ' . $e->getMessage());

            return response()->json([
                'status' => 'error',
                'message' => 'Registrasi gagal',
                'error' => $e->getMessage()
            ], 500)
                ->header('Content-Type', 'application/json');
        }
    }

    /**
     * Login user (support email atau username) with single device validation
     */
    public function login(LoginRequest $request): JsonResponse
    {
        try {
            $loginField = $request->login;
            $password = $request->password;

            // Tentukan apakah login menggunakan email atau username
            $fieldType = filter_var($loginField, FILTER_VALIDATE_EMAIL) ? 'email' : 'username';

            $credentials = [
                $fieldType => $loginField,
                'password' => $password
            ];

            // Find user first to check device
            $user = User::where($fieldType, $loginField)->first();

            if (!$user || !Hash::check($password, $user->password)) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Email/Username atau password salah'
                ], 401)
                    ->header('Content-Type', 'application/json');
            }

            if (!$user->is_active) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Akun Anda tidak aktif. Hubungi admin.',
                ], 403)
                    ->header('Content-Type', 'application/json');
            }

            // Generate device info
            $currentDeviceId = User::generateDeviceId($request);
            $currentDeviceName = User::getDeviceName($request->header('User-Agent', ''));

            // Check if user is already logged in from another device
            if ($user->isLoggedInFromAnotherDevice($currentDeviceId)) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Akun Anda sedang login di device lain. Hanya satu device yang diizinkan.',
                    'error_code' => 'DEVICE_ALREADY_LOGGED_IN',
                    'data' => [
                        'current_device' => $currentDeviceName,
                        'registered_device' => $user->device_name,
                        'last_login' => $user->last_login_at ? $user->last_login_at->format('Y-m-d H:i:s') : null,
                        'login_ip' => $user->last_login_ip
                    ],
                    'actions' => [
                        'force_login' => 'POST /api/v1/auth/force-login',
                        'logout_other_device' => 'POST /api/v1/auth/logout-other-device'
                    ]
                ], 409) // 409 Conflict
                    ->header('Content-Type', 'application/json');
            }

            // Proceed with normal login
            if (!$token = auth('api')->attempt($credentials)) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Email/Username atau password salah'
                ], 401)
                    ->header('Content-Type', 'application/json');
            }

            $user = auth('api')->user();

            // Update device info
            $user->updateDeviceInfo(
                $currentDeviceId,
                $currentDeviceName,
                $request->ip(),
                $request->header('User-Agent', '')
            );

            Log::info('User logged in successfully', [
                'user_id' => $user->id,
                'device_id' => $currentDeviceId,
                'device_name' => $currentDeviceName,
                'ip' => $request->ip()
            ]);

            return response()->json([
                'status' => 'success',
                'message' => 'Login berhasil',
                'data' => [
                    'user' => new UserResource($user->fresh()),
                    'access_token' => $token,
                    'token_type' => 'bearer',
                    'expires_in' => $this->getJWTExpiresIn(),
                    'expires_at' => now()->addMinutes(config('jwt.ttl'))->toDateTimeString(),
                    'device_info' => [
                        'device_id' => $currentDeviceId,
                        'device_name' => $currentDeviceName,
                        'login_time' => now()->format('Y-m-d H:i:s')
                    ]
                ]
            ], 200)
                ->header('Content-Type', 'application/json');

        } catch (JWTException $e) {
            Log::error('Login JWT error: ' . $e->getMessage());

            return response()->json([
                'status' => 'error',
                'message' => 'Tidak dapat membuat token',
                'error' => $e->getMessage()
            ], 500)
                ->header('Content-Type', 'application/json');
        } catch (\Exception $e) {
            Log::error('Login error: ' . $e->getMessage());

            return response()->json([
                'status' => 'error',
                'message' => 'Login gagal',
                'error' => $e->getMessage()
            ], 500)
                ->header('Content-Type', 'application/json');
        }
    }

    /**
     * Force login - logout from other device and login on current device
     */
    public function forceLogin(LoginRequest $request): JsonResponse
    {
        try {
            $loginField = $request->login;
            $password = $request->password;

            // Tentukan apakah login menggunakan email atau username
            $fieldType = filter_var($loginField, FILTER_VALIDATE_EMAIL) ? 'email' : 'username';

            // Find user
            $user = User::where($fieldType, $loginField)->first();

            if (!$user || !Hash::check($password, $user->password)) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Email/Username atau password salah'
                ], 401);
            }

            if (!$user->is_active) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Akun Anda tidak aktif. Hubungi admin.',
                ], 403);
            }

            // Generate device info for current device
            $currentDeviceId = User::generateDeviceId($request);
            $currentDeviceName = User::getDeviceName($request->header('User-Agent', ''));

            // Log the forced login
            Log::warning('Force login executed', [
                'user_id' => $user->id,
                'old_device' => $user->device_name,
                'old_device_id' => $user->device_id,
                'new_device' => $currentDeviceName,
                'new_device_id' => $currentDeviceId,
                'ip' => $request->ip()
            ]);

            // Force update device info (this will logout other device)
            $user->updateDeviceInfo(
                $currentDeviceId,
                $currentDeviceName,
                $request->ip(),
                $request->header('User-Agent', '')
            );

            // Create new token
            $token = auth('api')->login($user);

            return response()->json([
                'status' => 'success',
                'message' => 'Force login berhasil. Device lain telah di-logout.',
                'data' => [
                    'user' => new UserResource($user->fresh()),
                    'access_token' => $token,
                    'token_type' => 'bearer',
                    'expires_in' => $this->getJWTExpiresIn(),
                    'expires_at' => now()->addMinutes(config('jwt.ttl'))->toDateTimeString(),
                    'device_info' => [
                        'device_id' => $currentDeviceId,
                        'device_name' => $currentDeviceName,
                        'login_time' => now()->format('Y-m-d H:i:s')
                    ]
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Force login error: ' . $e->getMessage());

            return response()->json([
                'status' => 'error',
                'message' => 'Force login gagal',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Logout from other device only - Protected endpoint
     */
    public function logoutOtherDevice(Request $request): JsonResponse
    {
        try {
            $user = auth('api')->user();
            
            if (!$user) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'User not authenticated'
                ], 401);
            }

            // Get current device info
            $currentDeviceId = User::generateDeviceId($request);
            $currentDeviceName = User::getDeviceName($request->header('User-Agent', ''));

            // Check if user actually has another device logged in
            if (!$user->device_id) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Tidak ada device lain yang sedang login'
                ], 400);
            }

            // Check if current device is the same as registered device
            if ($user->device_id === $currentDeviceId) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Anda sedang login dari device yang terdaftar. Tidak ada device lain untuk di-logout.'
                ], 400);
            }

            // Store info about the device that will be logged out
            $deviceToLogout = [
                'device_id' => $user->device_id,
                'device_name' => $user->device_name,
                'last_login_ip' => $user->last_login_ip,
                'last_login_at' => $user->last_login_at ? $user->last_login_at->format('Y-m-d H:i:s') : null
            ];

            // Update user's device info to current device
            $user->updateDeviceInfo(
                $currentDeviceId,
                $currentDeviceName,
                $request->ip(),
                $request->header('User-Agent', '')
            );

            Log::info('Other device logged out by user', [
                'user_id' => $user->id,
                'logged_out_device' => $deviceToLogout,
                'new_device' => [
                    'device_id' => $currentDeviceId,
                    'device_name' => $currentDeviceName,
                    'ip' => $request->ip()
                ]
            ]);

            return response()->json([
                'status' => 'success',
                'message' => 'Device lain berhasil di-logout. Device Anda sekarang terdaftar sebagai device aktif.',
                'data' => [
                    'logged_out_device' => $deviceToLogout,
                    'current_device' => [
                        'device_name' => $currentDeviceName,
                        'login_time' => now()->format('Y-m-d H:i:s'),
                        'ip_address' => $request->ip()
                    ]
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Logout other device error: ' . $e->getMessage());

            return response()->json([
                'status' => 'error',
                'message' => 'Logout device lain gagal',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get all user sessions/devices
     */
    public function getUserSessions(Request $request): JsonResponse
    {
        try {
            $user = auth('api')->user();
            $currentDeviceId = User::generateDeviceId($request);
            
            $sessions = [];
            
            if ($user->device_id) {
                $sessions[] = [
                    'device_id' => $user->device_id,
                    'device_name' => $user->device_name,
                    'ip_address' => $user->last_login_ip,
                    'last_login_at' => $user->last_login_at ? $user->last_login_at->format('Y-m-d H:i:s') : null,
                    'is_current' => $user->device_id === $currentDeviceId,
                    'user_agent' => $user->user_agent
                ];
            }

            return response()->json([
                'status' => 'success',
                'message' => 'User sessions retrieved successfully',
                'data' => [
                    'user_id' => $user->id,
                    'total_active_sessions' => count($sessions),
                    'sessions' => $sessions,
                    'current_device_id' => $currentDeviceId
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Get user sessions error: ' . $e->getMessage());

            return response()->json([
                'status' => 'error',
                'message' => 'Gagal mengambil informasi sessions',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Send password reset link
     */
    public function forgotPassword(ForgotPasswordRequest $request): JsonResponse
    {
        try {
            $user = User::where('email', $request->email)->first();

            if (!$user) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'User dengan email tersebut tidak ditemukan'
                ], 404)
                    ->header('Content-Type', 'application/json');
            }

            if (!$user->is_active) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Akun Anda tidak aktif. Hubungi admin.'
                ], 403)
                    ->header('Content-Type', 'application/json');
            }

            // Generate reset token
            $token = Str::random(64);

            // Delete existing password reset tokens for this email
            DB::table('password_reset_tokens')
                ->where('email', $request->email)
                ->delete();

            // Create new password reset token
            DB::table('password_reset_tokens')->insert([
                'email' => $request->email,
                'token' => hash('sha256', $token),
                'created_at' => Carbon::now()
            ]);

            // Send reset password notification
            $user->notify(new ResetPasswordNotification($token, $request->email));

            return response()->json([
                'status' => 'success',
                'message' => 'Link reset password telah dikirim ke email Anda'
            ])
                ->header('Content-Type', 'application/json');
        } catch (\Exception $e) {
            Log::error('Forgot password error: ' . $e->getMessage());

            return response()->json([
                'status' => 'error',
                'message' => 'Gagal mengirim link reset password',
                'error' => $e->getMessage()
            ], 500)
                ->header('Content-Type', 'application/json');
        }
    }

    public function getAuth(): JsonResponse
    {
        $user = Auth::user();

        if (!$user) {
            return response()->json(
                [
                    'error' => 'User not authenticated',
                ],
                401,
            );
        }

        return response()->json(new UserResource($user));
    }

    /**
     * Reset password with token
     */
    public function resetPassword(ResetPasswordRequest $request): JsonResponse
    {
        try {
            // Check if token exists and not expired
            $passwordReset = DB::table('password_reset_tokens')
                ->where('email', $request->email)
                ->where('token', hash('sha256', $request->token))
                ->first();

            if (!$passwordReset) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Token reset password tidak valid'
                ], 400)
                    ->header('Content-Type', 'application/json');
            }

            // Check if token is expired (default 60 minutes)
            $expireTime = config('auth.passwords.users.expire', 60);
            if (Carbon::parse($passwordReset->created_at)->addMinutes($expireTime)->isPast()) {
                // Delete expired token
                DB::table('password_reset_tokens')
                    ->where('email', $request->email)
                    ->delete();

                return response()->json([
                    'status' => 'error',
                    'message' => 'Token reset password sudah kedaluwarsa'
                ], 400)
                    ->header('Content-Type', 'application/json');
            }

            // Update user password
            $user = User::where('email', $request->email)->first();

            if (!$user) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'User tidak ditemukan'
                ], 404)
                    ->header('Content-Type', 'application/json');
            }

            $user->password = Hash::make($request->password);
            
            // Clear device info when password is reset (force logout from all devices)
            $user->clearDeviceInfo();
            $user->save();

            // Delete the used token
            DB::table('password_reset_tokens')
                ->where('email', $request->email)
                ->delete();

            Log::info('Password reset successful', [
                'user_id' => $user->id,
                'email' => $user->email
            ]);

            return response()->json([
                'status' => 'success',
                'message' => 'Password berhasil direset. Silakan login ulang.'
            ])
                ->header('Content-Type', 'application/json');
        } catch (\Exception $e) {
            Log::error('Reset password error: ' . $e->getMessage());

            return response()->json([
                'status' => 'error',
                'message' => 'Gagal reset password',
                'error' => $e->getMessage()
            ], 500)
                ->header('Content-Type', 'application/json');
        }
    }

    /**
     * Verify reset password token
     */
    public function verifyResetToken(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'token' => 'required|string',
            'email' => 'required|email'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validasi gagal',
                'errors' => $validator->errors()
            ], 422)
                ->header('Content-Type', 'application/json');
        }

        try {
            $passwordReset = DB::table('password_reset_tokens')
                ->where('email', $request->email)
                ->where('token', hash('sha256', $request->token))
                ->first();

            if (!$passwordReset) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Token tidak valid',
                    'data' => ['valid' => false]
                ], 400)
                    ->header('Content-Type', 'application/json');
            }

            // Check if token is expired
            $expireTime = config('auth.passwords.users.expire', 60);
            $isExpired = Carbon::parse($passwordReset->created_at)->addMinutes($expireTime)->isPast();

            if ($isExpired) {
                // Delete expired token
                DB::table('password_reset_tokens')
                    ->where('email', $request->email)
                    ->delete();

                return response()->json([
                    'status' => 'error',
                    'message' => 'Token sudah kedaluwarsa',
                    'data' => ['valid' => false, 'expired' => true]
                ], 400)
                    ->header('Content-Type', 'application/json');
            }

            return response()->json([
                'status' => 'success',
                'message' => 'Token valid',
                'data' => [
                    'valid' => true,
                    'email' => $request->email,
                    'expires_at' => Carbon::parse($passwordReset->created_at)->addMinutes($expireTime)->toDateTimeString()
                ]
            ])
                ->header('Content-Type', 'application/json');
        } catch (\Exception $e) {
            Log::error('Verify reset token error: ' . $e->getMessage());

            return response()->json([
                'status' => 'error',
                'message' => 'Gagal memverifikasi token',
                'error' => $e->getMessage()
            ], 500)
                ->header('Content-Type', 'application/json');
        }
    }

    /**
     * Get authenticated user profile with device info
     */
    public function me(): JsonResponse
    {
        try {
            $user = auth('api')->user();

            return response()->json([
                'status' => 'success',
                'data' => [
                    'user' => new UserResource($user),
                    'device_info' => [
                        'device_id' => $user->device_id,
                        'device_name' => $user->device_name,
                        'last_login_at' => $user->last_login_at ? $user->last_login_at->format('Y-m-d H:i:s') : null,
                        'last_login_ip' => $user->last_login_ip
                    ]
                ]
            ])
                ->header('Content-Type', 'application/json');
        } catch (\Exception $e) {
            Log::error('Get user profile error: ' . $e->getMessage());

            return response()->json([
                'status' => 'error',
                'message' => 'Tidak dapat mengambil profil user',
                'error' => $e->getMessage()
            ], 500)
                ->header('Content-Type', 'application/json');
        }
    }

    /**
     * Update user profile
     */
    public function updateProfile(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|string|max:255',
            'university' => 'sometimes|string|max:255',
            'phone' => 'sometimes|string|max:20',
            'profile_image' => 'sometimes|image|mimes:jpeg,png,jpg|max:2048',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validasi gagal',
                'errors' => $validator->errors()
            ], 422)
                ->header('Content-Type', 'application/json');
        }

        try {
            $user = auth('api')->user();

            if ($request->hasFile('profile_image')) {
                // Delete old image if exists
                if ($user->profile_image && file_exists(storage_path('app/public/' . $user->profile_image))) {
                    unlink(storage_path('app/public/' . $user->profile_image));
                }

                $imagePath = $request->file('profile_image')->store('profile-images', 'public');
                $user->profile_image = $imagePath;
            }

            $user->fill($request->only(['name', 'university', 'phone']));
            $user->save();

            return response()->json([
                'status' => 'success',
                'message' => 'Profil berhasil diperbarui',
                'data' => [
                    'user' => new UserResource($user)
                ]
            ])
                ->header('Content-Type', 'application/json');
        } catch (\Exception $e) {
            Log::error('Update profile error: ' . $e->getMessage());

            return response()->json([
                'status' => 'error',
                'message' => 'Update profil gagal',
                'error' => $e->getMessage()
            ], 500)
                ->header('Content-Type', 'application/json');
        }
    }

    /**
     * Change password
     */
    public function changePassword(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'current_password' => 'required',
            'new_password' => 'required|string|min:8|confirmed',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validasi gagal',
                'errors' => $validator->errors()
            ], 422)
                ->header('Content-Type', 'application/json');
        }

        try {
            $user = auth('api')->user();

            if (!Hash::check($request->current_password, $user->password)) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Password saat ini salah',
                ], 422)
                    ->header('Content-Type', 'application/json');
            }

            $user->password = Hash::make($request->new_password);
            
            // Don't clear device info on password change, only on reset
            $user->save();

            Log::info('Password changed successfully', [
                'user_id' => $user->id
            ]);

            return response()->json([
                'status' => 'success',
                'message' => 'Password berhasil diubah',
            ])
                ->header('Content-Type', 'application/json');
        } catch (\Exception $e) {
            Log::error('Change password error: ' . $e->getMessage());

            return response()->json([
                'status' => 'error',
                'message' => 'Ubah password gagal',
                'error' => $e->getMessage()
            ], 500)
                ->header('Content-Type', 'application/json');
        }
    }

    /**
     * Logout user and clear device info
     */
    public function logout(): JsonResponse
    {
        try {
            $user = auth('api')->user();
            
            if ($user) {
                // Clear device info
                $user->clearDeviceInfo();
                
                Log::info('User logged out', [
                    'user_id' => $user->id,
                    'device_name' => $user->device_name
                ]);
            }

            auth('api')->logout();

            return response()->json([
                'status' => 'success',
                'message' => 'Berhasil logout'
            ])
                ->header('Content-Type', 'application/json');
        } catch (\Exception $e) {
            Log::error('Logout error: ' . $e->getMessage());

            return response()->json([
                'status' => 'error',
                'message' => 'Logout gagal',
                'error' => $e->getMessage()
            ], 500)
                ->header('Content-Type', 'application/json');
        }
    }

    /**
     * Check if token is valid and device matches
     */
    public function checkToken(Request $request): JsonResponse
    {
        try {
            $user = auth('api')->user();
            $payload = auth('api')->payload();

            // Validate current device
            $currentDeviceId = User::generateDeviceId($request);
            
            if ($user->device_id && $user->device_id !== $currentDeviceId) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Token tidak valid - device mismatch',
                    'error_code' => 'DEVICE_MISMATCH',
                    'data' => [
                        'valid' => false,
                        'device_mismatch' => true
                    ]
                ], 401)
                    ->header('Content-Type', 'application/json');
            }

            return response()->json([
                'status' => 'success',
                'message' => 'Token valid',
                'data' => [
                    'valid' => true,
                    'user_id' => $user->id,
                    'role' => $user->role,
                    'device_id' => $user->device_id,
                    'device_name' => $user->device_name,
                    'token_expires_at' => date('Y-m-d H:i:s', $payload['exp'])
                ]
            ])
                ->header('Content-Type', 'application/json');
        } catch (\Exception $e) {
            Log::error('Check token error: ' . $e->getMessage());

            return response()->json([
                'status' => 'error',
                'message' => 'Token tidak valid',
                'data' => [
                    'valid' => false
                ]
            ], 401)
                ->header('Content-Type', 'application/json');
        }
    }

    /**
     * Get device information
     */
    public function getDeviceInfo(Request $request): JsonResponse
    {
        try {
            $user = auth('api')->user();
            $currentDeviceId = User::generateDeviceId($request);
            $currentDeviceName = User::getDeviceName($request->header('User-Agent', ''));

            return response()->json([
                'status' => 'success',
                'data' => [
                    'current_device' => [
                        'device_id' => $currentDeviceId,
                        'device_name' => $currentDeviceName,
                        'ip_address' => $request->ip(),
                        'user_agent' => $request->header('User-Agent', '')
                    ],
                    'registered_device' => [
                        'device_id' => $user->device_id,
                        'device_name' => $user->device_name,
                        'last_login_at' => $user->last_login_at ? $user->last_login_at->format('Y-m-d H:i:s') : null,
                        'last_login_ip' => $user->last_login_ip
                    ],
                    'is_same_device' => $user->device_id === $currentDeviceId
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Get device info error: ' . $e->getMessage());

            return response()->json([
                'status' => 'error',
                'message' => 'Gagal mengambil informasi device',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get JWT expiration time in seconds
     */
    private function getJWTExpiresIn(): int
    {
        try {
            $ttlMinutes = config('jwt.ttl', 43200);
            return (int) $ttlMinutes * 60;
        } catch (\Exception $e) {
            return 2592000;
        }
    }
}