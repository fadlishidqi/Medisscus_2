<?php

use Illuminate\Support\Facades\Route;

// health check route
Route::get('/health', function () {
    return response()->json([
        'status' => 'success',
        'message' => 'Meddiscus API is running!',
        'timestamp' => now()->toDateTimeString(),
        'version' => '1.0.0'
    ]);
});

// load v1 routes
Route::prefix('v1')->group(function () {
    require base_path('routes/api/v1.php');
});

Route::fallback(function () {
    return response()->json([
        'status' => 'error',
        'message' => 'API endpoint not found'
    ], 404);
});
