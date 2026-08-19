<?php

use App\Http\Controllers\Admin\AdmissionController as AdminAdmissionController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\PaymentController;
use App\Http\Controllers\Admin\SeatAvailabilityController;
use App\Http\Controllers\Admin\StudentController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Public\AdmissionController as PublicAdmissionController;
use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')->name('home');

Route::get('/admission', [PublicAdmissionController::class, 'create'])
    ->name('admission.create');
Route::post('/admission', [PublicAdmissionController::class, 'store'])
    ->name('admission.store');

Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'create'])->name('login');
    Route::post('/login', [LoginController::class, 'store'])->name('login.store');
});

Route::post('/logout', [LoginController::class, 'destroy'])
    ->middleware('auth')
    ->name('logout');

Route::middleware(['auth', 'admin'])
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {
        Route::get('/dashboard', [DashboardController::class, 'index'])
            ->name('dashboard');

        Route::get('/admissions', [AdminAdmissionController::class, 'index'])
            ->name('admissions.index');
        Route::get('/admissions/{admission}', [AdminAdmissionController::class, 'show'])
            ->name('admissions.show');
        Route::post('/admissions/{admission}/approve', [AdminAdmissionController::class, 'approve'])
            ->name('admissions.approve');

        Route::get('/students', [StudentController::class, 'index'])
            ->name('students.index');
        Route::get('/students/{student}', [StudentController::class, 'show'])
            ->name('students.show');
        Route::post('/students/{student}/payments', [PaymentController::class, 'store'])
            ->name('students.payments.store');
        Route::get('/payments/{payment}/receipt', [PaymentController::class, 'receipt'])
            ->name('payments.receipt');

        Route::get('/available-seats', SeatAvailabilityController::class)
            ->name('seats.available');
    });
