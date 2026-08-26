<?php

use App\Http\Controllers\Admin\StaffController;
use App\Http\Controllers\Admin\JobController;
use App\Http\Controllers\Admin\CommunicationController;
use App\Http\Controllers\Admin\CmsController;
use Illuminate\Support\Facades\Route;

Route::middleware(['web','auth','admin','admin.branch'])->prefix('admin')->name('admin.')->group(function () {
    Route::middleware('permission:staff.manage')->group(function () {
        Route::patch('/staff/{staff}', [StaffController::class,'update'])->middleware('throttle:30,1')->name('staff.update');
        Route::delete('/staff/{staff}', [StaffController::class,'destroy'])->middleware('throttle:10,1')->name('staff.destroy');
    });
    Route::middleware('permission:jobs.manage')->group(function () {
        Route::patch('/jobs/{job}', [JobController::class,'update'])->middleware('throttle:30,1')->name('jobs.update');
        Route::delete('/jobs/{job}', [JobController::class,'destroy'])->middleware('throttle:10,1')->name('jobs.destroy');
    });
    Route::middleware(['permission:communications.manage','global-admin'])->group(function () {
        Route::patch('/communications/templates/{template}', [CommunicationController::class,'update'])->middleware('throttle:20,1')->name('communications.templates.update');
        Route::delete('/communications/templates/{template}', [CommunicationController::class,'destroy'])->middleware('throttle:10,1')->name('communications.templates.destroy');
    });
    Route::middleware(['permission:settings.manage','global-admin'])->group(function () {
        Route::patch('/cms/faqs/{faq}', [CmsController::class,'updateFaq'])->middleware('throttle:20,1')->name('cms.faqs.update');
        Route::delete('/cms/faqs/{faq}', [CmsController::class,'destroyFaq'])->middleware('throttle:10,1')->name('cms.faqs.destroy');
        Route::patch('/cms/testimonials/{testimonial}', [CmsController::class,'updateTestimonial'])->middleware('throttle:20,1')->name('cms.testimonials.update');
        Route::delete('/cms/testimonials/{testimonial}', [CmsController::class,'destroyTestimonial'])->middleware('throttle:10,1')->name('cms.testimonials.destroy');
    });
});
