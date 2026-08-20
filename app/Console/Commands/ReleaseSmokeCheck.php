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
        if (! is_link($publicStorage) && ! is_dir($publicStorage)) {
            $failures[] = 'public/storage is missing. Run php artisan storage:link if the host supports symlinks.';
        }

        try {
            DB::connection()->getPdo();
            DB::select('SELECT 1');
            $this->line('Database round-trip: OK');
        } catch (\Throwable $exception) {
            $failures[] = 'Database smoke check failed: '.$exception->getMessage();
        }

        $criticalRoutes = [
            'home',
            'login',
            'admin.dashboard',
            'admin.students.index',
            'admin.attendance.index',
            'admin.library.index',
            'admin.reports.index',
            'student.dashboard',
            'student.id-card',
            'digital-library.index',
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
            $this->info('Release smoke checks passed. This does not replace CI, release:preflight, migrations, or functional browser testing.');

            return self::SUCCESS;
        }

        foreach ($failures as $failure) {
            $this->error($failure);
        }

        $this->error('Release smoke checks failed. Keep the application closed to production traffic until these failures are resolved.');

        return self::FAILURE;
    }
}
