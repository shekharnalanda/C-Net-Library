<?php

use App\Http\Controllers\SeatScheduleController;
use Illuminate\Support\Facades\Route;

Route::middleware(['web','throttle:60,1'])->get('/seat-report',[SeatScheduleController::class,'index'])->name('seat-report');
Route::middleware(['web','auth','admin','admin.branch','permission:students.manage'])->prefix('admin')->name('admin.')->group(function(){
    Route::get('/seat-report',[SeatScheduleController::class,'index'])->name('seat-report');
    Route::delete('/seat-allocations/{allocation}',[SeatScheduleController::class,'destroy'])->middleware('throttle:20,1')->name('seat-allocations.destroy');
});
