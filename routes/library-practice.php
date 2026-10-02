<?php

use App\Http\Controllers\Student\LibraryPracticeController;
use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'auth:library_student', 'student'])->prefix('student')->name('student.')->group(function () {
    Route::get('/online-practice', [LibraryPracticeController::class, 'index'])->name('library-practice');
    Route::post('/online-practice', [LibraryPracticeController::class, 'launch'])->middleware('throttle:10,1')->name('library-practice.launch');
});
