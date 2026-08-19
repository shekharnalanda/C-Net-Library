<?php

use App\Http\Controllers\Admin\AdmissionController as AdminAdmissionController;
use App\Http\Controllers\Admin\AttendanceController;
use App\Http\Controllers\Admin\CommunicationController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\DigitalResourceController;
use App\Http\Controllers\Admin\EnquiryController as AdminEnquiryController;
use App\Http\Controllers\Admin\JobController as AdminJobController;
use App\Http\Controllers\Admin\LibraryController;
use App\Http\Controllers\Admin\MembershipRenewalController;
use App\Http\Controllers\Admin\PaymentController;
use App\Http\Controllers\Admin\ReportsController;
use App\Http\Controllers\Admin\SeatAvailabilityController;
use App\Http\Controllers\Admin\SettingsController;
use App\Http\Controllers\Admin\StaffController;
use App\Http\Controllers\Admin\StudentController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Public\AdmissionController as PublicAdmissionController;
use App\Http\Controllers\Public\EnquiryController as PublicEnquiryController;
use App\Http\Controllers\Public\JobController as PublicJobController;
use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')->name('home');

Route::get('/admission', [PublicAdmissionController::class, 'create'])->name('admission.create');
Route::post('/admission', [PublicAdmissionController::class, 'store'])->name('admission.store');
Route::get('/enquiry', [PublicEnquiryController::class, 'create'])->name('enquiry.create');
Route::post('/enquiry', [PublicEnquiryController::class, 'store'])->name('enquiry.store');
Route::get('/jobs', [PublicJobController::class, 'index'])->name('jobs.index');

Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'create'])->name('login');
    Route::post('/login', [LoginController::class, 'store'])->name('login.store');
});

Route::post('/logout', [LoginController::class, 'destroy'])->middleware('auth')->name('logout');

Route::middleware(['auth', 'admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/reports', [ReportsController::class, 'index'])->name('reports.index');

    Route::get('/admissions', [AdminAdmissionController::class, 'index'])->name('admissions.index');
    Route::get('/admissions/{admission}', [AdminAdmissionController::class, 'show'])->name('admissions.show');
    Route::post('/admissions/{admission}/approve', [AdminAdmissionController::class, 'approve'])->name('admissions.approve');

    Route::get('/enquiries', [AdminEnquiryController::class, 'index'])->name('enquiries.index');
    Route::patch('/enquiries/{enquiry}', [AdminEnquiryController::class, 'update'])->name('enquiries.update');
    Route::post('/enquiries/{enquiry}/convert', [AdminEnquiryController::class, 'convert'])->name('enquiries.convert');

    Route::get('/students', [StudentController::class, 'index'])->name('students.index');
    Route::get('/students/{student}', [StudentController::class, 'show'])->name('students.show');
    Route::get('/students/{student}/renew', [MembershipRenewalController::class, 'create'])->name('students.renew.create');
    Route::post('/students/{student}/renew', [MembershipRenewalController::class, 'store'])->name('students.renew.store');
    Route::post('/students/{student}/payments', [PaymentController::class, 'store'])->name('students.payments.store');
    Route::get('/payments/{payment}/receipt', [PaymentController::class, 'receipt'])->name('payments.receipt');

    Route::get('/attendance', [AttendanceController::class, 'index'])->name('attendance.index');
    Route::post('/students/{student}/check-in', [AttendanceController::class, 'checkIn'])->name('attendance.check-in');
    Route::post('/students/{student}/check-out', [AttendanceController::class, 'checkOut'])->name('attendance.check-out');

    Route::get('/library', [LibraryController::class, 'index'])->name('library.index');
    Route::post('/library/issue', [LibraryController::class, 'issue'])->name('library.issue');
    Route::post('/library/issues/{bookIssue}/return', [LibraryController::class, 'return'])->name('library.return');

    Route::get('/digital-library', [DigitalResourceController::class, 'index'])->name('digital-resources.index');
    Route::post('/digital-library', [DigitalResourceController::class, 'store'])->name('digital-resources.store');

    Route::get('/jobs', [AdminJobController::class, 'index'])->name('jobs.index');
    Route::post('/jobs', [AdminJobController::class, 'store'])->name('jobs.store');

    Route::get('/communications', [CommunicationController::class, 'index'])->name('communications.index');
    Route::post('/communications/templates', [CommunicationController::class, 'store'])->name('communications.templates.store');

    Route::get('/staff', [StaffController::class, 'index'])->name('staff.index');
    Route::post('/staff', [StaffController::class, 'store'])->name('staff.store');
    Route::post('/staff/{staff}/attendance', [StaffController::class, 'attendance'])->name('staff.attendance');
    Route::patch('/staff/leaves/{staffLeave}', [StaffController::class, 'leave'])->name('staff.leaves.update');
    Route::post('/staff/{staff}/payroll', [StaffController::class, 'payroll'])->name('staff.payroll');

    Route::get('/settings', [SettingsController::class, 'index'])->name('settings.index');
    Route::patch('/settings', [SettingsController::class, 'update'])->name('settings.update');

    Route::get('/available-seats', SeatAvailabilityController::class)->name('seats.available');
});
