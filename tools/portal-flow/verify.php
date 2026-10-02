<?php

use App\Http\Controllers\Public\AdmissionController;
use App\Services\LibraryPracticeBridge;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\ViewErrorBag;

[$script,$root,$site] = $argv;
require $root.'/vendor/autoload.php';
$app = require $root.'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

view()->share('errors', new ViewErrorBag);
if ($site === 'library') {
    foreach (['admission.availability', 'student.login', 'student.logout', 'student.device-otp', 'student.device-confirm', 'admin.student-report'] as $name) {
        if (! Route::has($name)) {
            throw new RuntimeException('Portal route missing: '.$name);
        }
    }
    foreach (['library_student_sessions', 'library_portal_mail', 'library_device_recovery'] as $table) {
        if (! Schema::hasTable($table)) {
            throw new RuntimeException('Portal table missing.');
        }
    }
    if (! Schema::hasColumn('admissions', 'preferred_seat_id') || ! Schema::hasColumn('admissions', 'photo')) {
        throw new RuntimeException('Admission selection migration missing.');
    }
    $guard = Route::getRoutes()->getByName('student.dashboard')->gatherMiddleware();
    if (! in_array('auth:library_student', $guard, true)) {
        throw new RuntimeException('Student guard not separated.');
    }
    if (config('auth.guards.library_student.driver') !== 'session') {
        throw new RuntimeException('Student session guard missing.');
    }
    if (is_file($root.'/routes/mci-account-recovery.php') && ! Route::has('mci.recovery')) {
        throw new RuntimeException('Previously installed account recovery route was not preserved.');
    }
    $admission = app(AdmissionController::class)->create()->render();
    $disk = Storage::disk('public');
    $disk->makeDirectory('student-photos');
    if (! $disk->exists('student-photos') || realpath($root.'/public/storage') !== realpath($disk->path(''))) {
        throw new RuntimeException('Student photo storage/public link requires review.');
    }
    if (! str_contains($admission, 'photoCamera') || ! str_contains($admission, 'photoGallery') || ! is_file($root.'/public/js/admission-photo.js')) {
        throw new RuntimeException('Admission photo controls missing.');
    }
    $login = view('auth.student-login')->render();
    if (! str_contains($login, 'Student Login')) {
        throw new RuntimeException('Login page rendering failed.');
    }
    $report = view('admin.student-report', ['students' => new LengthAwarePaginator([], 0, 30), 'records' => [], 'branches' => collect(), 'format' => 'pdf', 'mailStatus' => collect()])->render();
    if (! str_contains($report, 'Student Report')) {
        throw new RuntimeException('Report rendering failed.');
    }
    $schedule = $app->make(Schedule::class);
    $commands = collect($schedule->events())->map(fn ($e) => $e->command ?? '')->implode(' ');
    if (! str_contains($commands, 'library:fee-reminders') || ! str_contains($commands, 'library:deliver-mail')) {
        throw new RuntimeException('Reminder/mail schedule missing.');
    }
    echo "LIBRARY_PORTAL_SCHEMA_GUARD_VIEWS_SCHEDULE_OK\n";
} else {
    $bridge = app(LibraryPracticeBridge::class);
    $bridge->identity(0, 'VERIFY-NONEXISTENT', 0);
    if (! Schema::connection('library_practice_source')->hasTable('library_student_sessions')) {
        throw new RuntimeException('Cross-site session table missing.');
    }
    foreach (['student_id', 'token_hash', 'practice_session_hash', 'practice_expires_at'] as $col) {
        if (! Schema::connection('library_practice_source')->hasColumn('library_student_sessions', $col)) {
            throw new RuntimeException('Cross-site session column missing.');
        }
    }
    DB::connection('library_practice_source')->table('library_student_sessions')->whereRaw('1 = 0')->update(['last_seen_at' => DB::raw('last_seen_at')]);
    foreach (['library-practice.index', 'library-practice.start', 'library-practice.attempts.submit'] as $name) {
        if (! Route::has($name)) {
            throw new RuntimeException('Test route missing.');
        }
    }
    if (! method_exists($bridge, 'touchPractice')) {
        throw new RuntimeException('Test device enforcement missing.');
    }
    echo "TEST_PORTAL_SESSION_SCHEMA_AND_ROUTES_OK\n";
}
