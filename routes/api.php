<?php

use App\Http\Controllers\Api\Mobile\AuthController;
use App\Http\Controllers\Api\Mobile\StudentActivityController;
use App\Http\Controllers\Api\Mobile\StudentController;
use Illuminate\Support\Facades\Route;

Route::prefix('mobile/v1')->group(function () {
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:10,1');

    Route::middleware(['mobile.auth', 'throttle:120,1'])->group(function () {
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::get('/dashboard', [StudentController::class, 'dashboard']);
        Route::get('/profile', [StudentController::class, 'profile']);
        Route::get('/membership', [StudentActivityController::class, 'membership']);
        Route::get('/payments', [StudentActivityController::class, 'payments']);
        Route::get('/attendance', [StudentActivityController::class, 'attendance']);
        Route::get('/seat-allocation', [StudentActivityController::class, 'seat']);
    });
});
