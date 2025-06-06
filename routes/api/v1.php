<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\QuestionBankController;
use App\Http\Controllers\Api\ProgramController;
use App\Http\Controllers\Api\EnrollmentController;

// Auth routes
Route::prefix('auth')->group(function () {
    // Public routes
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login', [AuthController::class, 'login']);
    Route::post('/force-login', [AuthController::class, 'forceLogin']);
    Route::post('/forgot-password', [AuthController::class, 'forgotPassword']);
    Route::post('/reset-password', [AuthController::class, 'resetPassword']);
    Route::post('/verify-reset-token', [AuthController::class, 'verifyResetToken']);
    
    // Protected routes - JWT only (untuk logout other device)
    Route::middleware(['auth:api'])->group(function () {
        Route::post('/logout-other-device', [AuthController::class, 'logoutOtherDevice']);
        Route::get('/sessions', [AuthController::class, 'getUserSessions']);
    });
    
    // Protected routes - JWT + Device validation
    Route::middleware(['auth:api', 'validate.device'])->group(function () {
        Route::get('/get-auth', [AuthController::class, 'getAuth']);
        Route::get('/me', [AuthController::class, 'me']);
        Route::put('/profile', [AuthController::class, 'updateProfile']);
        Route::post('/change-password', [AuthController::class, 'changePassword']);
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::get('/check-token', [AuthController::class, 'checkToken']);
        Route::get('/device-info', [AuthController::class, 'getDeviceInfo']);
    });
});

// Programs routes
Route::prefix('programs')->group(function () {
    Route::get('/', [ProgramController::class, 'index']);
    Route::get('/{identifier}', [ProgramController::class, 'show']);
   
    // Admin only routes (create, update, delete programs)
    Route::middleware(['auth:api', 'validate.device', 'admin'])->group(function () {
        Route::post('/', [ProgramController::class, 'store']);
        Route::put('/{id}', [ProgramController::class, 'update']);
        Route::delete('/{id}', [ProgramController::class, 'destroy']);
    });
});

// Enrollments routes
Route::prefix('enrollments')->middleware(['auth:api', 'validate.device', 'role:admin,user'])->group(function () {
    Route::get('/', [EnrollmentController::class, 'index']);
    Route::post('/', [EnrollmentController::class, 'store']);
    Route::get('/{id}', [EnrollmentController::class, 'show']);
    Route::put('/{id}', [EnrollmentController::class, 'update']);
    Route::delete('/{id}', [EnrollmentController::class, 'destroy']);
});

// Question banks routes - Both admin and user can access
Route::prefix('question-banks')->middleware(['auth:api', 'validate.device', 'role:admin,user'])->group(function () {
    Route::get('/', [QuestionBankController::class, 'index']);
    Route::post('/', [QuestionBankController::class, 'store']);
    Route::get('/{id}', [QuestionBankController::class, 'show']);
    Route::put('/{id}', [QuestionBankController::class, 'update']);
    Route::delete('/{id}', [QuestionBankController::class, 'destroy']);
});

// User management routes - Admin only
Route::prefix('users')->middleware(['auth:api', 'validate.device', 'admin'])->group(function () {
    Route::get('/', function () {
        return response()->json([
            'status' => 'success',
            'message' => 'Admin can access user management'
        ]);
    });
    
    Route::get('/{id}', function ($id) {
        return response()->json([
            'status' => 'success',
            'message' => "Admin can view user {$id}"
        ]);
    });
});

// User specific routes - User only
Route::prefix('user')->middleware(['auth:api', 'validate.device', 'user'])->group(function () {
    Route::get('/my-enrollments', function () {
        return response()->json([
            'status' => 'success',
            'message' => 'User can view their own enrollments'
        ]);
    });
});