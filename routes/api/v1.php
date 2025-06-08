<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\QuestionBankController;
use App\Http\Controllers\Api\ProgramController;
use App\Http\Controllers\Api\EnrollmentController;
use App\Http\Controllers\QuestionController;
use App\Http\Controllers\TryoutController;
use App\Http\Controllers\UserController;

// Auth routes
Route::prefix('auth')->group(function () {
    // public routes
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login', [AuthController::class, 'login']);
    Route::post('/forgot-password', [AuthController::class, 'forgotPassword']);
    Route::post('/reset-password', [AuthController::class, 'resetPassword']);
    Route::post('/verify-reset-token', [AuthController::class, 'verifyResetToken']);

    // protected routes
    Route::middleware(['auth:api'])->group(function () {
        Route::get('/get-auth', [AuthController::class, 'getAuth']);
        Route::get('/me', [AuthController::class, 'me']);
        Route::put('/profile', [AuthController::class, 'updateProfile']);
        Route::post('/change-password', [AuthController::class, 'changePassword']);
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::get('/check-token', [AuthController::class, 'checkToken']);
    });
}); // TUTUP auth group di sini

// Programs routes - PINDAHKAN KELUAR dari auth group
Route::prefix('programs')->group(function () {
    // Public routes
    Route::get('/active', [ProgramController::class, 'getActivateProgram']);
    Route::get('/{identifier}', [ProgramController::class, 'show']);

    // Protected routes - only admin
    Route::middleware(['auth:api'])->group(function () {
        Route::get('/', [ProgramController::class, 'index']);
        Route::post('/', [ProgramController::class, 'store']);
        Route::put('/{id}', [ProgramController::class, 'update']);
        Route::delete('/{id}', [ProgramController::class, 'destroy']);
    });
});

// Enrollments routes - PINDAHKAN KELUAR dari auth group
Route::prefix('enrollments')->middleware(['auth:api'])->group(function () {
    Route::get('/', [EnrollmentController::class, 'index']);
    Route::post('/', [EnrollmentController::class, 'store']);
    Route::get('/stats', [EnrollmentController::class, 'stats']);
    Route::get('/{id}', [EnrollmentController::class, 'show']);
    Route::put('/{id}', [EnrollmentController::class, 'update']);
    Route::delete('/{id}', [EnrollmentController::class, 'destroy']);
    Route::get('/programs/{program_id}', [EnrollmentController::class, 'getByProgram']);
});

// Question banks routes
Route::prefix('question-banks')->group(function () {
    Route::middleware(['auth:api'])->group(function () {
        Route::get('/', [QuestionBankController::class, 'index']);
        Route::post('/', [QuestionBankController::class, 'store']);
        Route::get('/{id}', [QuestionBankController::class, 'show']);
        Route::put('/{id}', [QuestionBankController::class, 'update']);
        Route::delete('/{id}', [QuestionBankController::class, 'destroy']);
    });
});

// Question routes
Route::prefix('questions')->group(function () {
    Route::middleware(['auth:api'])->group(function () {
        Route::post('/', [QuestionController::class, 'store']);
        Route::get('/bank/{question_bank_id}', [QuestionController::class, 'getFromQuestionBanks']);
        Route::get('/{question_bank_id}', [QuestionController::class, 'index']);
        Route::get('/show/{id}', [QuestionController::class, 'show']);
        Route::put('/{id}', [QuestionController::class, 'update']);
        Route::delete('/{id}', [QuestionController::class, 'destroy']);
    });
});

Route::prefix('tryouts')->middleware(['auth:api'])->group(function () {
    Route::middleware(['auth:api'])->group(function () {
        Route::get('/{program_id}', [TryoutController::class, 'index']);
        Route::get('/', [TryoutController::class, 'getAllTryouts']);
        Route::post('/', [TryoutController::class, 'create']);
        Route::get('/show/{id}', [TryoutController::class, 'show']);
        Route::get('/questions/{id}', [TryoutController::class, 'getQuestion']);
        // Route::put('/{id}', [App\Http\Controllers\TryoutController::class, 'update']);
        // Route::delete('/{id}', [App\Http\Controllers\TryoutController::class, 'destroy']);
    });
});

Route::prefix('users')->group(function () {
    Route::middleware(['auth:api'])->group(function () {
        Route::get('/', [UserController::class, 'index']);
        Route::get('/{id}', [UserController::class, 'show']);
    });
});
