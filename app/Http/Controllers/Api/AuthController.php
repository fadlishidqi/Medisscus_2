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
                'role' => $request->role ?? 'user',
                'password' => Hash::make($request->password),
            ]);

            event(new Registered($user));

            $token = JWTAuth::fromUser($user);

            return response()->json([
                'status' => 'success',
                'message' => 'Registrasi berhasil',
                'data' => [
                    'user' => new UserResource($user),
                    'access_token' => $token,
                    'token_type' => 'bearer',
                    'expires_in' => $this->getJWTExpiresIn(),
                    'expires_at' => now()->addMinutes(config('jwt.ttl'))->toDateTimeString()
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
     * Login user (support email atau username)
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

            if (!$token = auth('api')->attempt($credentials)) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Email/Username atau password salah'
                ], 401)
                    ->header('Content-Type', 'application/json');
            }

            $user = auth('api')->user();

            if (!$user->is_active) {
                auth('api')->logout();
                return response()->json([
                    'status' => 'error',
                    'message' => 'Akun Anda tidak aktif. Hubungi admin.',
                ], 403)
                    ->header('Content-Type', 'application/json');
            }

            return response()->json([
                'id' => $user->id,
                'token' => $token,
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
            $user->save();

            // Delete the used token
            DB::table('password_reset_tokens')
                ->where('email', $request->email)
                ->delete();

            return response()->json([
                'status' => 'success',
                'message' => 'Password berhasil direset'
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
     * Get authenticated user profile
     */
    public function me(): JsonResponse
    {
        try {
            $user = auth('api')->user();

            return response()->json([
                'status' => 'success',
                'data' => [
                    'user' => new UserResource($user)
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
            $user->save();

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
     * Logout user
     */
    public function logout(): JsonResponse
    {
        try {
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
     * Check if token is valid
     */
    public function checkToken(): JsonResponse
    {
        try {
            $user = auth('api')->user();
            $payload = auth('api')->payload();

            return response()->json([
                'status' => 'success',
                'message' => 'Token valid',
                'data' => [
                    'valid' => true,
                    'user_id' => $user->id,
                    'role' => $user->role,
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
