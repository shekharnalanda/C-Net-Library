<?php

use App\Http\Controllers\Admin\StudentController;
use Illuminate\Support\Facades\Route;

Route::middleware(['web','auth','admin','admin.branch','permission:students.manage'])
    ->prefix('admin')
    ->name('admin.')
    ->group(function (): void {
        Route::post('/students', [StudentController::class, 'store'])
            ->middleware('throttle:30,1')
            ->name('students.store');

        Route::patch('/students/{student}', [StudentController::class, 'update'])
            ->middleware('throttle:60,1')
            ->name('students.update');

        Route::delete('/students/{student}', [StudentController::class, 'destroy'])
            ->middleware('throttle:20,1')
            ->name('students.destroy');
    });
