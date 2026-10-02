<?php

use App\Http\Controllers\Admin\LibraryStudentReportController as Report;
use App\Http\Controllers\Auth\LibraryStudentLoginController as Login;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Public\AdmissionAvailabilityController;
use Illuminate\Support\Facades\Route;

Route::middleware('web')->group(function () {
    Route::get('/admission/availability', AdmissionAvailabilityController::class)->middleware('throttle:60,1')->name('admission.availability');
    Route::get('/student-login', [Login::class, 'create'])->name('student.login');
    Route::post('/student-login', [Login::class, 'store'])->middleware('throttle:15,1')->name('student.login.store');
    Route::post('/student-login/device-otp', [Login::class, 'recovery'])->middleware('throttle:3,10')->name('student.device-otp');
    Route::post('/student-login/device-confirm', [Login::class, 'recoverConfirm'])->middleware('throttle:10,10')->name('student.device-confirm');
    Route::post('/student/logout', [Login::class, 'logout'])->middleware(['auth:library_student'])->name('student.logout');
    Route::post('/student/password', [Login::class, 'password'])->middleware(['auth:library_student', 'student', 'throttle:5,1'])->name('student.password');
    Route::get('/admin-login', [LoginController::class, 'create'])->name('admin.login');
    Route::middleware(['auth:web', 'admin', 'admin.branch', 'permission:reports.view'])->group(function () {
        Route::get('/admin/student-report', [Report::class, 'index'])->name('admin.student-report');
        Route::post('/admin/student-report/{student}/resend-welcome', [Report::class, 'resend'])->middleware('throttle:5,1')->name('admin.student-report.resend');
    });
    Route::post('/admin/students/{student}/close-session', [Report::class, 'closeSession'])->middleware(['auth:web', 'admin', 'admin.branch', 'permission:students.manage', 'throttle:10,1'])->name('admin.student-session.close');
});
