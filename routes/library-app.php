<?php

use App\Http\Controllers\LibraryAppController;
use Illuminate\Support\Facades\Route;

Route::get('/library-app.webmanifest', [LibraryAppController::class, 'manifest'])->name('library.app.manifest');
Route::get('/library-app/icon/{size}.png', [LibraryAppController::class, 'icon'])->where('size', '180|192|512')->name('library.app.icon');
