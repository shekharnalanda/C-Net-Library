<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

class ReleaseSmokeCheck extends Command
{
    protected $signature = 'release:smoke';

    protected $description = 'Run non-destructive post-deployment application smoke checks.';

    public function handle(): int
    {
        $failures = [];

        if (! config('app.key')) {
            $failures[] = 'APP_KEY is missing.';
        }

        foreach ([storage_path(), storage_path('framework'), storage_path('logs'), base_path('bootstrap/cache')] as $path) {
            if (! is_dir($path) || ! is_writable($path)) {
                $failures[] = "Required writable path is unavailable: {$path}.";
            }
        }

        $publicStorage = public_path('storage');
        $expectedStorage = storage_path('app/public');

        if (! file_exists($publicStorage) && ! is_link($publicStorage)) {
            $failures[] = 'public/storage is missing. Run php artisan storage:link if the host supports symlinks.';
        } elseif (is_link($publicStorage)) {
            $resolvedPublicStorage = realpath($publicStorage);
            $resolvedExpectedStorage = realpath($expectedStorage);

            if ($resolvedPublicStorage === false || $resolvedExpectedStorage === false || $resolvedPublicStorage !== $resolvedExpectedStorage) {
                $failures[] = 'public/storage symlink does not resolve to storage/app/public.';
            }
        } elseif (! is_dir($publicStorage)) {
            $failures[] = 'public/storage exists but is not a directory or symlink.';
        }

        try {
            DB::connection()->getPdo();
            DB::select('SELECT 1');
            $this->line('Database round-trip: OK');
        } catch (\Throwable $exception) {
            $failures[] = 'Database smoke check failed: '.$exception->getMessage();
        }

        $criticalRoutes = [
            'home', 'login', 'admission.create', 'admission.store', 'enquiry.create',
            'digital-library.index', 'jobs.index',
            'student.dashboard', 'student.id-card', 'student.saved-jobs.index',
            'admin.dashboard', 'admin.admissions.index', 'admin.enquiries.index',
            'admin.students.index', 'admin.students.id-cards.bulk',
            'admin.branches.index', 'admin.study-space.index', 'admin.seats.available', 'admin.lockers.index',
            'admin.attendance.index', 'admin.attendance.scan',
            'admin.expenses.index', 'admin.library.index', 'admin.digital-resources.index',
            'admin.jobs.index', 'admin.communications.index', 'admin.staff.index',
            'admin.reports.index', 'admin.settings.index', 'admin.cms.index', 'admin.security.index',
            'api.mobile.v1.login', 'api.mobile.v1.dashboard', 'api.mobile.v1.profile',
            'api.mobile.v1.membership', 'api.mobile.v1.payments', 'api.mobile.v1.attendance',
            'api.mobile.v1.seat-allocation', 'api.mobile.v1.books', 'api.mobile.v1.issued-books',
            'api.mobile.v1.digital-resources', 'api.mobile.v1.jobs', 'api.mobile.v1.qr-member-id',
            'api.mobile.v1.support',
            'api.mobile.v1.admin.login', 'api.mobile.v1.admin.logout',
            'api.mobile.v1.admin.dashboard', 'api.mobile.v1.admin.students',
            'api.mobile.v1.admin.enquiries', 'api.mobile.v1.admin.payments',
            'api.mobile.v1.admin.attendance', 'api.mobile.v1.admin.books',
            'api.mobile.v1.admin.book-issues', 'api.mobile.v1.admin.lockers',
        ];

        foreach ($criticalRoutes as $routeName) {
            if (! Route::has($routeName)) {
                $failures[] = "Critical route is not registered: {$routeName}.";
            }
        }

        try {
            Artisan::call('schedule:list');
            $schedule = Artisan::output();

            foreach (['memberships:expire-due', 'memberships:activate-scheduled'] as $command) {
                if (! str_contains($schedule, $command)) {
                    $failures[] = "Scheduled command is not registered: {$command}.";
                }
            }
        } catch (\Throwable $exception) {
            $failures[] = 'Unable to inspect Laravel schedule: '.$exception->getMessage();
        }

        if ($failures === []) {
            $this->info('Release smoke checks passed for public, student, admin, branches, study-space, locker and mobile API routes.');
            return self::SUCCESS;
        }

        foreach ($failures as $failure) {
            $this->error($failure);
        }

        $this->error('Release smoke checks failed. Do not treat the release as final until these failures are resolved.');
        return self::FAILURE;
    }
}
