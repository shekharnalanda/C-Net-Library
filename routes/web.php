<?php

use App\Http\Controllers\Admin\AdmissionController as AdminAdmissionController;
use App\Http\Controllers\Admin\AttendanceController;
use App\Http\Controllers\Admin\BulkStudentIdCardController;
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
use App\Http\Controllers\Admin\StudySpaceController;
use App\Http\Controllers\Auth\FirstAdminSetupController;
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

    Route::middleware('throttle:6,1')->group(function () {
        Route::get('/setup-admin', [FirstAdminSetupController::class, 'create'])->name('setup-admin.create');
        Route::post('/setup-admin', [FirstAdminSetupController::class, 'store'])->name('setup-admin.store');
    });

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
        Route::post('/admissions/{admission}/approve', [AdminAdmissionController::class, 'approve'])->middleware('throttle:20,1')->name('admissions.approve');
    });

    Route::middleware('permission:enquiries.manage')->group(function () {
        Route::get('/enquiries', [AdminEnquiryController::class, 'index'])->name('enquiries.index');
        Route::patch('/enquiries/{enquiry}', [AdminEnquiryController::class, 'update'])->name('enquiries.update');
        Route::post('/enquiries/{enquiry}/convert', [AdminEnquiryController::class, 'convert'])->middleware('throttle:20,1')->name('enquiries.convert');
    });

    Route::middleware('permission:students.manage')->group(function () {
        Route::get('/students', [StudentController::class, 'index'])->name('students.index');
        Route::get('/students/id-cards/bulk', [BulkStudentIdCardController::class, 'index'])->name('students.id-cards.bulk');
        Route::post('/students/id-cards/bulk/print', [BulkStudentIdCardController::class, 'print'])->middleware('throttle:10,1')->name('students.id-cards.bulk.print');
        Route::get('/students/{student}', [StudentController::class, 'show'])->name('students.show');
        Route::get('/students/{student}/id-card', [StudentController::class, 'idCard'])->name('students.id-card');
        Route::post('/students/{student}/photo', [StudentController::class, 'updatePhoto'])->middleware('throttle:10,1')->name('students.photo.update');
        Route::get('/students/{student}/renew', [MembershipRenewalController::class, 'create'])->name('students.renew.create');
        Route::post('/students/{student}/renew', [MembershipRenewalController::class, 'store'])->middleware('throttle:20,1')->name('students.renew.store');
        Route::post('/students/{student}/rotate-qr', [StudentController::class, 'rotateQr'])->middleware('throttle:10,1')->name('students.rotate-qr');
        Route::get('/available-seats', SeatAvailabilityController::class)->name('seats.available');

        Route::get('/study-space', [StudySpaceController::class, 'index'])->name('study-space.index');
        Route::post('/study-space/halls', [StudySpaceController::class, 'storeHall'])->middleware('throttle:30,1')->name('study-space.halls.store');
        Route::patch('/study-space/halls/{hall}', [StudySpaceController::class, 'updateHall'])->middleware('throttle:30,1')->name('study-space.halls.update');
        Route::delete('/study-space/halls/{hall}', [StudySpaceController::class, 'destroyHall'])->middleware('throttle:20,1')->name('study-space.halls.destroy');
        Route::post('/study-space/seats', [StudySpaceController::class, 'storeSeat'])->middleware('throttle:60,1')->name('study-space.seats.store');
        Route::patch('/study-space/seats/{seat}', [StudySpaceController::class, 'updateSeat'])->middleware('throttle:60,1')->name('study-space.seats.update');
        Route::delete('/study-space/seats/{seat}', [StudySpaceController::class, 'destroySeat'])->middleware('throttle:30,1')->name('study-space.seats.destroy');
        Route::post('/study-space/slots', [StudySpaceController::class, 'storeSlot'])->middleware('throttle:30,1')->name('study-space.slots.store');
        Route::patch('/study-space/slots/{slot}', [StudySpaceController::class, 'updateSlot'])->middleware('throttle:30,1')->name('study-space.slots.update');
        Route::post('/study-space/plans', [StudySpaceController::class, 'storePlan'])->middleware('throttle:30,1')->name('study-space.plans.store');
        Route::patch('/study-space/plans/{plan}', [StudySpaceController::class, 'updatePlan'])->middleware('throttle:30,1')->name('study-space.plans.update');
        Route::post('/study-space/allocations', [StudySpaceController::class, 'allocate'])->middleware('throttle:60,1')->name('study-space.allocations.store');
        Route::patch('/study-space/allocations/{allocation}', [StudySpaceController::class, 'updateAllocation'])->middleware('throttle:60,1')->name('study-space.allocations.update');
    });

    Route::middleware('permission:payments.manage')->group(function () {
        Route::post('/students/{student}/payments', [PaymentController::class, 'store'])->middleware('throttle:30,1')->name('students.payments.store');
        Route::get('/payments/{payment}/receipt', [PaymentController::class, 'receipt'])->name('payments.receipt');
        Route::post('/payments/{payment}/adjustments', [PaymentController::class, 'adjust'])->middleware('throttle:20,1')->name('payments.adjustments.store');
        Route::get('/cashbook', [ExpenseController::class, 'index'])->name('expenses.index');
        Route::post('/cashbook', [ExpenseController::class, 'store'])->middleware('throttle:30,1')->name('expenses.store');
        Route::post('/cashbook/{expense}/adjustments', [ExpenseController::class, 'adjust'])->middleware('throttle:20,1')->name('expenses.adjustments.store');
    });

    Route::middleware('permission:attendance.manage')->group(function () {
        Route::get('/attendance', [AttendanceController::class, 'index'])->name('attendance.index');
        Route::post('/students/{student}/check-in', [AttendanceController::class, 'checkIn'])->middleware('throttle:30,1')->name('attendance.check-in');
        Route::post('/students/{student}/check-out', [AttendanceController::class, 'checkOut'])->middleware('throttle:30,1')->name('attendance.check-out');
        Route::get('/attendance/qr/{token}', [QrAttendanceController::class, 'landing'])
            ->whereUuid('token')
            ->middleware('throttle:60,1')
            ->name('attendance.qr');
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
        Route::post('/library/issue', [LibraryController::class, 'issue'])->middleware('throttle:30,1')->name('library.issue');
        Route::post('/library/reservations', [LibraryController::class, 'reserve'])->middleware('throttle:30,1')->name('library.reservations.store');
        Route::post('/library/reservations/{reservation}/cancel', [LibraryController::class, 'cancelReservation'])->middleware('throttle:30,1')->name('library.reservations.cancel');
        Route::post('/library/issues/{bookIssue}/return', [LibraryController::class, 'return'])->middleware('throttle:30,1')->name('library.return');
        Route::post('/library/issues/{bookIssue}/lost', [LibraryController::class, 'lost'])->middleware('throttle:20,1')->name('library.lost');
        Route::post('/library/issues/{bookIssue}/charges', [LibraryController::class, 'collectCharge'])->middleware('throttle:30,1')->name('library.charges.store');
        Route::post('/library/issues/{bookIssue}/recover', [LibraryController::class, 'recoverLost'])->middleware('throttle:20,1')->name('library.recover');
    });

    Route::middleware('permission:digital-library.manage')->group(function () {
        Route::get('/digital-library', [DigitalResourceController::class, 'index'])->name('digital-resources.index');
        Route::post('/digital-library', [DigitalResourceController::class, 'store'])->middleware('throttle:20,1')->name('digital-resources.store');
        Route::patch('/digital-library/{resource}', [DigitalResourceController::class, 'update'])->middleware('throttle:20,1')->name('digital-resources.update');
        Route::delete('/digital-library/{resource}', [DigitalResourceController::class, 'destroy'])->middleware('throttle:10,1')->name('digital-resources.destroy');
    });

    Route::middleware('permission:jobs.manage')->group(function () {
        Route::get('/jobs', [AdminJobController::class, 'index'])->name('jobs.index');
        Route::post('/jobs', [AdminJobController::class, 'store'])->middleware('throttle:30,1')->name('jobs.store');
    });

    Route::middleware('permission:communications.manage')->group(function () {
        Route::get('/communications', [CommunicationController::class, 'index'])->name('communications.index');
        Route::post('/communications/templates', [CommunicationController::class, 'store'])
            ->middleware(['global-admin', 'throttle:20,1'])
            ->name('communications.templates.store');
    });

    Route::middleware('permission:staff.manage')->group(function () {
        Route::get('/staff', [StaffController::class, 'index'])->name('staff.index');
        Route::post('/staff', [StaffController::class, 'store'])->middleware('throttle:30,1')->name('staff.store');
        Route::post('/staff/{staff}/attendance', [StaffController::class, 'attendance'])->middleware('throttle:30,1')->name('staff.attendance');
        Route::patch('/staff/leaves/{staffLeave}', [StaffController::class, 'leave'])->middleware('throttle:20,1')->name('staff.leaves.update');
        Route::post('/staff/{staff}/payroll', [StaffController::class, 'payroll'])->middleware('throttle:20,1')->name('staff.payroll');
    });

    Route::middleware(['permission:settings.manage', 'global-admin'])->group(function () {
        Route::get('/settings', [SettingsController::class, 'index'])->name('settings.index');
        Route::patch('/settings', [SettingsController::class, 'update'])->middleware('throttle:20,1')->name('settings.update');
        Route::get('/cms', [CmsController::class, 'index'])->name('cms.index');
        Route::patch('/cms/pages/{page}', [CmsController::class, 'updatePage'])->middleware('throttle:20,1')->name('cms.pages.update');
        Route::post('/cms/faqs', [CmsController::class, 'storeFaq'])->middleware('throttle:20,1')->name('cms.faqs.store');
        Route::post('/cms/testimonials', [CmsController::class, 'storeTestimonial'])->middleware('throttle:20,1')->name('cms.testimonials.store');
        Route::post('/cms/gallery', [CmsController::class, 'storeGallery'])->middleware('throttle:10,1')->name('cms.gallery.store');
        Route::delete('/cms/gallery/{galleryItem}', [CmsController::class, 'destroyGallery'])->middleware('throttle:10,1')->name('cms.gallery.destroy');
    });

    Route::middleware(['permission:roles.manage', 'global-admin'])->group(function () {
        Route::get('/security', [SecurityController::class, 'index'])->name('security.index');
        Route::patch('/security/roles/{role}', [SecurityController::class, 'updateRole'])->middleware('throttle:20,1')->name('security.roles.update');
        Route::patch('/security/users/{user}/roles', [SecurityController::class, 'updateUserRoles'])->middleware('throttle:20,1')->name('security.users.roles.update');
    });
});
