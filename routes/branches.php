<?php

use App\Http\Controllers\Admin\BranchController;
use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'auth', 'admin', 'admin.branch', 'permission:settings.manage', 'global-admin'])
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {
        Route::get('/branches', [BranchController::class, 'index'])->name('branches.index');
        Route::post('/branches', [BranchController::class, 'store'])->middleware('throttle:20,1')->name('branches.store');
        Route::patch('/branches/{branch}', [BranchController::class, 'update'])->middleware('throttle:30,1')->name('branches.update');
    });
