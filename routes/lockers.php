<?php

use App\Http\Controllers\Admin\LockerController;
use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'auth', 'admin', 'admin.branch', 'permission:students.manage'])
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {
        Route::get('/lockers', [LockerController::class, 'index'])->name('lockers.index');
        Route::post('/lockers', [LockerController::class, 'store'])->middleware('throttle:30,1')->name('lockers.store');
        Route::patch('/lockers/{locker}', [LockerController::class, 'update'])->middleware('throttle:30,1')->name('lockers.update');
        Route::post('/lockers/allocations', [LockerController::class, 'allocate'])->middleware('throttle:60,1')->name('lockers.allocations.store');
        Route::post('/lockers/allocations/{allocation}/payments', [LockerController::class, 'collectPayment'])->middleware('throttle:60,1')->name('lockers.allocations.payments.store');
        Route::patch('/lockers/allocations/{allocation}', [LockerController::class, 'updateAllocation'])->middleware('throttle:60,1')->name('lockers.allocations.update');
    });
