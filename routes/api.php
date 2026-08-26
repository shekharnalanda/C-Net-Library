<?php

use App\Http\Controllers\Api\Mobile\AdminAuthController;
use App\Http\Controllers\Api\Mobile\AdminController;
use App\Http\Controllers\Api\Mobile\AuthController;
use App\Http\Controllers\Api\Mobile\LibraryContentController;
use App\Http\Controllers\Api\Mobile\StudentActivityController;
use App\Http\Controllers\Api\Mobile\StudentController;
use Illuminate\Support\Facades\Route;

Route::prefix('mobile/v1')->name('api.mobile.v1.')->group(function () {
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:10,1')->name('login');

    Route::middleware(['mobile.auth', 'throttle:120,1'])->group(function () {
        Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
        Route::get('/dashboard', [StudentController::class, 'dashboard'])->name('dashboard');
        Route::get('/profile', [StudentController::class, 'profile'])->name('profile');
        Route::get('/membership', [StudentActivityController::class, 'membership'])->name('membership');
        Route::get('/payments', [StudentActivityController::class, 'payments'])->name('payments');
        Route::get('/attendance', [StudentActivityController::class, 'attendance'])->name('attendance');
        Route::get('/seat-allocation', [StudentActivityController::class, 'seat'])->name('seat-allocation');
        Route::get('/books', [LibraryContentController::class, 'books'])->name('books');
        Route::get('/issued-books', [LibraryContentController::class, 'issuedBooks'])->name('issued-books');
        Route::get('/digital-resources', [LibraryContentController::class, 'digitalResources'])->name('digital-resources');
        Route::get('/jobs', [LibraryContentController::class, 'jobs'])->name('jobs');
        Route::get('/qr-member-id', [LibraryContentController::class, 'qrMemberId'])->name('qr-member-id');
        Route::post('/support', [LibraryContentController::class, 'support'])->middleware('throttle:10,1')->name('support');
    });

    Route::prefix('admin')->name('admin.')->group(function () {
        Route::post('/login', [AdminAuthController::class, 'login'])->middleware('throttle:10,1')->name('login');

        Route::middleware(['mobile.auth', 'throttle:120,1'])->group(function () {
            Route::post('/logout', [AdminAuthController::class, 'logout'])->name('logout');
            Route::get('/dashboard', [AdminController::class, 'dashboard'])->name('dashboard');
            Route::get('/students', [AdminController::class, 'students'])->name('students');
            Route::get('/enquiries', [AdminController::class, 'enquiries'])->name('enquiries');
            Route::get('/payments', [AdminController::class, 'payments'])->name('payments');
            Route::get('/attendance', [AdminController::class, 'attendance'])->name('attendance');
            Route::get('/books', [AdminController::class, 'books'])->name('books');
            Route::get('/book-issues', [AdminController::class, 'bookIssues'])->name('book-issues');
            Route::get('/lockers', [AdminController::class, 'lockers'])->name('lockers');
        });
    });
});
