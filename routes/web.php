<?php

use App\Http\Controllers\Admin\AdmissionController as AdminAdmissionController;
use App\Http\Controllers\Admin\AttendanceController;
use App\Http\Controllers\Admin\CmsController;
use App\Http\Controllers\Admin\CommunicationController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\DigitalResourceController;
use App\Http\Controllers\Admin\EnquiryController as AdminEnquiryController;
use App\Http\Controllers\Admin\ExpenseController;
use App\Http\Controllers\Admin\JobController as AdminJobController;
use App\Http\Controllers\Admin\LibraryController;
use App\Http\Controllers\Admin\MembershipRenewalController;
use App\Http\Controllers\Admin\PaymentController;
use App\Http\Controllers\Admin\QrAttendanceController;
use App\Http\Controllers\Admin\ReportsController;
use App\Http\Controllers\Admin\SeatAvailabilityController;
use App\Http\Controllers\Admin\SecurityController;
use App\Http\Controllers\Admin\SettingsController;
use App\Http\Controllers\Admin\StaffController;
use App\Http\Controllers\Admin\StudentController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\StudentActivationController;
use App\Http\Controllers\Public\AdmissionController as PublicAdmissionController;
use App\Http\Controllers\Public\DigitalLibraryController as PublicDigitalLibraryController;
use App\Http\Controllers\Public\DigitalResourceAccessController;
use App\Http\Controllers\Public\EnquiryController as PublicEnquiryController;
use App\Http\Controllers\Public\HomeController;
use App\Http\Controllers\Public\JobController as PublicJobController;
use App\Http\Controllers\Public\JobRedirectController;
use App\Http\Controllers\Student\DashboardController as StudentDashboardController;
use App\Http\Controllers\Student\SavedJobController;
use Illuminate\Support\Facades\Route;

Route::get('/', [HomeController::class, 'index'])->name('home');
Route::get('/page/{page:slug}', [HomeController::class, 'page'])->name('public.page');
Route::get('/digital-library', [PublicDigitalLibraryController::class, 'index'])->name('digital-library.index');
Route::get('/digital-library/resources/{resource}', DigitalResourceAccessController::class)
    ->middleware('throttle:60,1')
    ->name('digital-library.access');

Route::get('/admission', [PublicAdmissionController::class, 'create'])->name('admission.create');
Route::post('/admission', [PublicAdmissionController::class, 'store'])->middleware('throttle:8,1')->name('admission.store');
Route::get('/enquiry', [PublicEnquiryController::class, 'create'])->name('enquiry.create');
Route::post('/enquiry', [PublicEnquiryController::class, 'store'])->middleware('throttle:10,1')->name('enquiry.store');
Route::get('/jobs', [PublicJobController::class, 'index'])->name('jobs.index');
Route::get('/jobs/{job}/official', JobRedirectController::class)
    ->middleware('throttle:60,1')
    ->name('jobs.official');

Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'create'])->name('login');
    Route::post('/login', [LoginController::class, 'store'])->name('login.store');

    Route::middleware('throttle:10,1')->group(function () {
        Route::get('/student/activate/{token}', [StudentActivationController::class, 'show'])->name('student.activate');
        Route::post('/student/activate/{token}', [StudentActivationController::class, 'store'])->name('student.activate.store');
    });
});

Route::post('/logout', [LoginController::class, 'destroy'])->middleware('auth')->name('logout');

Route::middleware(['auth', 'student'])->prefix('student')->name('student.')->group(function () {
    Route::get('/dashboard', [StudentDashboardController::class, 'index'])->name('dashboard');
    Route::get('/id-card', [StudentDashboardController::class, 'idCard'])->name('id-card');
    Route::get('/saved-jobs', [SavedJobController::class, 'index'])->name('saved-jobs.index');
    Route::post('/saved-jobs/{job}', [SavedJobController::class, 'store'])->middleware('throttle:30,1')->name('saved-jobs.store');
    Route::delete('/saved-jobs/{job}', [SavedJobController::class, 'destroy'])->middleware('throttle:30,1')->name('saved-jobs.destroy');
});

Route::middleware(['auth', 'admin', 'admin.branch'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->middleware('permission:dashboard.view')->name('dashboard');
    Route::get('/reports', [ReportsController::class, 'index'])->middleware('permission:reports.view')->name('reports.index');

    Route::middleware('permission:admissions.manage')->group(function () {
        Route::get('/admissions', [AdminAdmissionController::class, 'index'])->name('admissions.index');
        Route::get('/admissions/{admission}', [AdminAdmissionController::class, 'show'])->name('admissions.show');
        Route::post('/admissions/{admission}/approve', [AdminAdmissionController::class, 'approve'])->name('admissions.approve');
    });

    Route::middleware('permission:enquiries.manage')->group(function () {
        Route::get('/enquiries', [AdminEnquiryController::class, 'index'])->name('enquiries.index');
        Route::patch('/enquiries/{enquiry}', [AdminEnquiryController::class, 'update'])->name('enquiries.update');
        Route::post('/enquiries/{enquiry}/convert', [AdminEnquiryController::class, 'convert'])->name('enquiries.convert');
    });

    Route::middleware('permission:students.manage')->group(function () {
        Route::get('/students', [StudentController::class, 'index'])->name('students.index');
        Route::get('/students/{student}', [StudentController::class, 'show'])->name('students.show');
        Route::get('/students/{student}/renew', [MembershipRenewalController::class, 'create'])->name('students.renew.create');
        Route::post('/students/{student}/renew', [MembershipRenewalController::class, 'store'])->name('students.renew.store');
        Route::get('/available-seats', SeatAvailabilityController::class)->name('seats.available');
    });

    Route::middleware('permission:payments.manage')->group(function () {
        Route::post('/students/{student}/payments', [PaymentController::class, 'store'])->name('students.payments.store');
        Route::get('/payments/{payment}/receipt', [PaymentController::class, 'receipt'])->name('payments.receipt');
        Route::post('/payments/{payment}/adjustments', [PaymentController::class, 'adjust'])->middleware('throttle:20,1')->name('payments.adjustments.store');
        Route::get('/cashbook', [ExpenseController::class, 'index'])->name('expenses.index');
        Route::post('/cashbook', [ExpenseController::class, 'store'])->middleware('throttle:30,1')->name('expenses.store');
    });

    Route::middleware('permission:attendance.manage')->group(function () {
        Route::get('/attendance', [AttendanceController::class, 'index'])->name('attendance.index');
        Route::post('/students/{student}/check-in', [AttendanceController::class, 'checkIn'])->name('attendance.check-in');
        Route::post('/students/{student}/check-out', [AttendanceController::class, 'checkOut'])->name('attendance.check-out');
        Route::get('/attendance/scan', [QrAttendanceController::class, 'scan'])->name('attendance.scan');
        Route::post('/attendance/scan', [QrAttendanceController::class, 'lookup'])
            ->middleware('throttle:30,1')
            ->name('attendance.scan.lookup');
        Route::post('/attendance/scan/{student}', [QrAttendanceController::class, 'mark'])
            ->middleware('throttle:30,1')
            ->name('attendance.scan.mark');
    });

    Route::middleware('permission:library.manage')->group(function () {
        Route::get('/library', [LibraryController::class, 'index'])->name('library.index');
        Route::post('/library/issue', [LibraryController::class, 'issue'])->name('library.issue');
        Route::post('/library/issues/{bookIssue}/return', [LibraryController::class, 'return'])->name('library.return');
    });

    Route::middleware('permission:digital-library.manage')->group(function () {
        Route::get('/digital-library', [DigitalResourceController::class, 'index'])->name('digital-resources.index');
        Route::post('/digital-library', [DigitalResourceController::class, 'store'])->name('digital-resources.store');
        Route::patch('/digital-library/{resource}', [DigitalResourceController::class, 'update'])->name('digital-resources.update');
        Route::delete('/digital-library/{resource}', [DigitalResourceController::class, 'destroy'])->name('digital-resources.destroy');
    });

    Route::middleware('permission:jobs.manage')->group(function () {
        Route::get('/jobs', [AdminJobController::class, 'index'])->name('jobs.index');
        Route::post('/jobs', [AdminJobController::class, 'store'])->name('jobs.store');
    });

    Route::middleware('permission:communications.manage')->group(function () {
        Route::get('/communications', [CommunicationController::class, 'index'])->name('communications.index');
        Route::post('/communications/templates', [CommunicationController::class, 'store'])
            ->middleware('global-admin')
            ->name('communications.templates.store');
    });

    Route::middleware('permission:staff.manage')->group(function () {
        Route::get('/staff', [StaffController::class, 'index'])->name('staff.index');
        Route::post('/staff', [StaffController::class, 'store'])->name('staff.store');
        Route::post('/staff/{staff}/attendance', [StaffController::class, 'attendance'])->name('staff.attendance');
        Route::patch('/staff/leaves/{staffLeave}', [StaffController::class, 'leave'])->name('staff.leaves.update');
        Route::post('/staff/{staff}/payroll', [StaffController::class, 'payroll'])->name('staff.payroll');
    });

    Route::middleware(['permission:settings.manage', 'global-admin'])->group(function () {
        Route::get('/settings', [SettingsController::class, 'index'])->name('settings.index');
        Route::patch('/settings', [SettingsController::class, 'update'])->name('settings.update');
        Route::get('/cms', [CmsController::class, 'index'])->name('cms.index');
        Route::patch('/cms/pages/{page}', [CmsController::class, 'updatePage'])->name('cms.pages.update');
        Route::post('/cms/faqs', [CmsController::class, 'storeFaq'])->name('cms.faqs.store');
        Route::post('/cms/testimonials', [CmsController::class, 'storeTestimonial'])->name('cms.testimonials.store');
        Route::post('/cms/gallery', [CmsController::class, 'storeGallery'])->name('cms.gallery.store');
        Route::delete('/cms/gallery/{galleryItem}', [CmsController::class, 'destroyGallery'])->name('cms.gallery.destroy');
    });

    Route::middleware(['permission:roles.manage', 'global-admin'])->group(function () {
        Route::get('/security', [SecurityController::class, 'index'])->name('security.index');
        Route::patch('/security/roles/{role}', [SecurityController::class, 'updateRole'])->name('security.roles.update');
        Route::patch('/security/users/{user}/roles', [SecurityController::class, 'updateUserRoles'])->name('security.users.roles.update');
    });
});
