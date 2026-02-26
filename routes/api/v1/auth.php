<?php

use App\Http\Controllers\Api\Auth\AuthController;
use App\Http\Controllers\Api\Auth\VerificationController;
use Illuminate\Support\Facades\Route;

Route::prefix('auth')->group(function () {
    // Public Routes
    Route::post('/register', [AuthController::class, 'register']);

    // Email Verification (signed URL — no auth needed)
    Route::get('/email/verify/{id}/{hash}', [VerificationController::class, 'verify'])
        ->middleware(['auth:sanctum', 'signed'])
        ->name('verification.verify');

    // Authenticated Routes
    Route::middleware('auth:sanctum')->group(function () {
        // Resend verification email
        Route::post('/email/resend', [VerificationController::class, 'resend'])
            ->middleware('throttle:6,1');

        // Steps 2-4 require verified email
        Route::middleware('verified')->group(function () {
            Route::post('/select-role', [AuthController::class, 'selectRole']);
            Route::post('/complete-profile', [AuthController::class, 'completeProfile']);
            Route::post('/select-interests', [AuthController::class, 'selectInterests']);
        });
    });
});
