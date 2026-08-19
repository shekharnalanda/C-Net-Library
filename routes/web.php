<?php

use App\Http\Controllers\Admin\AdmissionController as AdminAdmissionController;
use App\Http\Controllers\Admin\SeatAvailabilityController;
use App\Http\Controllers\Public\AdmissionController as PublicAdmissionController;
use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')->name('home');

Route::get('/admission', [PublicAdmissionController::class, 'create'])
    ->name('admission.create');
Route::post('/admission', [PublicAdmissionController::class, 'store'])
    ->name('admission.store');

Route::prefix('admin')->name('admin.')->group(function () {
    Route::get('/admissions', [AdminAdmissionController::class, 'index'])
        ->name('admissions.index');
    Route::get('/admissions/{admission}', [AdminAdmissionController::class, 'show'])
        ->name('admissions.show');
    Route::post('/admissions/{admission}/approve', [AdminAdmissionController::class, 'approve'])
        ->name('admissions.approve');

    Route::get('/available-seats', SeatAvailabilityController::class)
        ->name('seats.available');
});
