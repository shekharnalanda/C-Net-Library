<?php

use App\Http\Controllers\Admin\SlotController;
use Illuminate\Support\Facades\Route;

Route::middleware(['web','auth','admin','admin.branch','permission:students.manage'])
    ->prefix('admin')->name('admin.')->group(function(){
        Route::get('/slot-management',[SlotController::class,'index'])->name('slots.index');
    });
