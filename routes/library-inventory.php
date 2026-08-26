<?php

use App\Http\Controllers\Admin\LibraryInventoryController;
use Illuminate\Support\Facades\Route;

Route::middleware(['web','auth','admin','admin.branch','permission:library.manage'])
    ->prefix('admin/library-inventory')->name('admin.library-inventory.')->group(function () {
        Route::get('/', [LibraryInventoryController::class,'index'])->name('index');
        Route::post('/categories', [LibraryInventoryController::class,'storeCategory'])->middleware('throttle:30,1')->name('categories.store');
        Route::patch('/categories/{category}', [LibraryInventoryController::class,'updateCategory'])->middleware('throttle:30,1')->name('categories.update');
        Route::delete('/categories/{category}', [LibraryInventoryController::class,'destroyCategory'])->middleware('throttle:15,1')->name('categories.destroy');
        Route::post('/books', [LibraryInventoryController::class,'storeBook'])->middleware('throttle:30,1')->name('books.store');
        Route::patch('/books/{book}', [LibraryInventoryController::class,'updateBook'])->middleware('throttle:30,1')->name('books.update');
        Route::delete('/books/{book}', [LibraryInventoryController::class,'destroyBook'])->middleware('throttle:15,1')->name('books.destroy');
        Route::post('/copies', [LibraryInventoryController::class,'storeCopy'])->middleware('throttle:60,1')->name('copies.store');
        Route::post('/copies/bulk', [LibraryInventoryController::class,'bulkStoreCopies'])->middleware('throttle:20,1')->name('copies.bulk');
        Route::patch('/copies/{copy}', [LibraryInventoryController::class,'updateCopy'])->middleware('throttle:60,1')->name('copies.update');
        Route::delete('/copies/{copy}', [LibraryInventoryController::class,'destroyCopy'])->middleware('throttle:30,1')->name('copies.destroy');
    });
