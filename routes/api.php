<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\API\V1\AuthController;
use App\Http\Controllers\API\V1\AdminController;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');


Route::prefix('v1')->group(callback: function () {
    // User Authentication
    Route::prefix('user')->group(function () {
        Route::post('/register', [AuthController::class, 'createAccount'])->name('auth.register');
        Route::post('/login', [AuthController::class, 'login'])->name('auth.login');
        Route::post('/social-auth', [AuthController::class, 'socialAuth'])->name('auth.social-auth');
        Route::post('/forgot-password', [AuthController::class, 'sendOtp'])->name('auth.forgot-password');
        Route::post('/resend-otp', [AuthController::class, 'sendOTP'])->name('auth.resend-otp');
        Route::post('/verify-otp', [AuthController::class, 'verifyOtp'])->name('auth.verify-otp');
        Route::post('/verify-id', [AuthController::class, 'verifyId']);
        Route::post('/verify-username', [AuthController::class, 'verifyUsername']);
        Route::post('/verify-email', [AuthController::class, 'verifyEmail']);
        Route::post('/verify-refcode', [AuthController::class, 'verifyReferralCode']);
    });

    Route::prefix('admin')->group(function () {
        // Route::post('/create-level', [AdminController::class, 'createLevel']);
        // Route::get('/get-levels/{id?}', [AdminController::class, 'getLevels']);
        // Route::put('/update-level/{id}', [AdminController::class, 'updateLevel']);
        Route::prefix('levels')->group(function () {
            Route::post('/', [AdminController::class, 'createLevel']);
            Route::get('/{id?}', [AdminController::class, 'getLevels']);
            Route::put('/{id}', [AdminController::class, 'updateLevel']);
            Route::delete('/{id}', [AdminController::class, 'deleteLevel']);
        });

        // Route::post('/levels', [AdminController::class, 'createLevel']);

        // // Get all Levels or a specific Level by ID
        // Route::get('/levels/{id?}', [AdminController::class, 'getLevels']);

        // // Update a specific Level by ID
        // Route::put('/levels/{id}', [AdminController::class, 'updateLevel']);

        // // Delete a specific Level by ID
        // Route::delete('/levels/{id}', [AdminController::class, 'deleteLevel']);
    });

});
