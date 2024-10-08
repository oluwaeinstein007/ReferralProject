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
        Route::prefix('levels')->group(function () {
            Route::post('/', [AdminController::class, 'createLevel']);
            Route::get('/{id?}', [AdminController::class, 'getLevels']);
            Route::put('/{id}', [AdminController::class, 'updateLevel']);
            Route::delete('/{id}', [AdminController::class, 'deleteLevel']);
        });

        Route::prefix('customer')->group(function () {
            Route::get('/activities/{id?}', [AdminController::class, 'getUserActivities'])->name('admin.get-users-activity');
            Route::get('/metadata', [AdminController::class, 'getUserMetadata'])->name('admin.get-users-metadata');
            Route::get('/{id?}', [AdminController::class, 'getUsers'])->name('admin.get-user');
            Route::delete('/delete/{id}', [AdminController::class, 'deleteUser'])->name('user.close-account');
            Route::post('/restore/{id}', [AdminController::class, 'restoreUser'])->name('user.restore-account');
            Route::post('/suspend/{id}', [AdminController::class, 'suspendUser'])->name('user.suspend-account');
            Route::get('/analytics-month-chart', [AdminController::class, 'revenueAnalyticsChart']);
        });
    });

});
