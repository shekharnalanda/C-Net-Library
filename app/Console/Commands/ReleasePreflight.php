<?php

namespace App\Console\Commands;

use App\Models\Expense;
use App\Models\LibraryChargePayment;
use App\Models\Payment;
use App\Models\Payroll;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ReleasePreflight extends Command
{
    protected $signature = 'release:preflight';

    protected $description = 'Run non-destructive production release readiness checks.';

    public function handle(): int
    {
        $failures = [];

        if (! file_exists(base_path('composer.lock'))) {
            $failures[] = 'composer.lock is missing.';
        }

        if (! file_exists(base_path('vendor/autoload.php'))) {
            $failures[] = 'vendor/autoload.php is missing. Install exact production dependencies from composer.lock before deployment.';
        }

        if (! extension_loaded('pdo_mysql')) {
            $failures[] = 'PHP extension pdo_mysql is required.';
        }

        foreach (['mbstring', 'openssl', 'fileinfo', 'tokenizer', 'ctype', 'iconv'] as $extension) {
            if (! extension_loaded($extension)) {
                $failures[] = "Required PHP extension is missing: {$extension}.";
            }
        }

        if (config('app.timezone') !== 'Asia/Kolkata') {
            $failures[] = 'APP timezone must be Asia/Kolkata for date-based memberships, attendance and scheduler behavior.';
        }
        if (config('app.env') !== 'production') {
            $failures[] = 'APP_ENV must resolve to production.';
        }
        if ((bool) config('app.debug')) {
            $failures[] = 'APP_DEBUG must be false in production.';
        }
        if (! config('app.key')) {
            $failures[] = 'APP_KEY is missing.';
        }

        $appUrl = (string) config('app.url');
        if (! str_starts_with(strtolower($appUrl), 'https://')) {
            $failures[] = 'APP_URL must use HTTPS.';
        }
        if (config('session.driver') !== 'database') {
            $failures[] = 'SESSION_DRIVER must resolve to database for the production runtime design.';
        }
        if (! (bool) config('session.encrypt')) {
            $failures[] = 'SESSION_ENCRYPT must resolve to true in production.';
        }
        if (! (bool) config('session.secure')) {
            $failures[] = 'SESSION_SECURE_COOKIE must resolve to true.';
        }
        if (! (bool) config('session.http_only')) {
            $failures[] = 'Session cookies must be HttpOnly.';
        }
        if (! in_array(strtolower((string) config('session.same_site')), ['lax', 'strict'], true)) {
            $failures[] = 'SESSION_SAME_SITE must resolve to lax or strict.';
        }
        if (config('cache.default') !== 'database') {
            $failures[] = 'CACHE_STORE must resolve to database for scheduler locks and runtime consistency.';
        }
        if (config('queue.default') !== 'database') {
            $failures[] = 'QUEUE_CONNECTION must resolve to database for the production runtime design.';
        }

        foreach ([storage_path(), storage_path('framework'), storage_path('logs'), base_path('bootstrap/cache')] as $path) {
            if (! is_dir($path) || ! is_writable($path)) {
                $failures[] = "Required writable path is unavailable: {$path}.";
            }
        }

        try {
            DB::connection()->getPdo();
            $this->line('Database connection: OK');
        } catch (\Throwable $exception) {
            $failures[] = 'Database connection failed: '.$exception->getMessage();
            return $this->finish($failures);
        }

        $runtimeTables = ['sessions', 'cache', 'cache_locks', 'jobs', 'job_batches', 'failed_jobs'];
        $businessTables = [
            'users', 'branches', 'students', 'admissions', 'enquiries',
            'study_halls', 'seats', 'study_slots', 'fee_plans', 'student_memberships', 'seat_allocations',
            'payments', 'payment_adjustments', 'expenses', 'expense_adjustments',
            'attendances', 'staff', 'staff_attendances', 'staff_leaves', 'payrolls',
            'book_categories', 'books', 'book_copies', 'book_issues', 'library_charge_payments',
            'digital_resources', 'digital_resource_logs', 'saved_jobs',
            'lockers', 'locker_allocations', 'locker_payments',
            'mobile_api_tokens',
        ];

        foreach ($runtimeTables as $table) {
            if (! Schema::hasTable($table)) {
                $failures[] = "Required runtime table is missing: {$table}.";
            }
        }
        foreach ($businessTables as $table) {
            if (! Schema::hasTable($table)) {
                $failures[] = "Required business table is missing: {$table}. Migration state is incomplete.";
            }
        }

        try {
            Artisan::call('migrate:status');
            $migrationStatus = Artisan::output();
            if (preg_match('/\|\s*Pending\s*\|/i', $migrationStatus) || preg_match('/\bPending\b/i', $migrationStatus)) {
                $failures[] = 'One or more database migrations are pending.';
            }
        } catch (\Throwable $exception) {
            $failures[] = 'Unable to inspect migration status: '.$exception->getMessage();
        }

        $duplicateChecks = [
            ['table' => 'payments', 'column' => 'transaction_ref', 'model' => Payment::class],
            ['table' => 'expenses', 'column' => 'transaction_ref', 'model' => Expense::class],
            ['table' => 'payrolls', 'column' => 'transaction_ref', 'model' => Payroll::class],
            ['table' => 'library_charge_payments', 'column' => 'transaction_ref', 'model' => LibraryChargePayment::class],
        ];

        foreach ($duplicateChecks as $check) {
            if (! Schema::hasTable($check['table']) || ! Schema::hasColumn($check['table'], $check['column'])) {
                continue;
            }
            $model = $check['model'];
            $count = $model::query()
                ->whereNotNull($check['column'])
                ->where($check['column'], '<>', '')
                ->select($check['column'])
                ->groupBy($check['column'])
                ->havingRaw('COUNT(*) > 1')
                ->count();
            if ($count > 0) {
                $failures[] = "Duplicate non-empty transaction references found in {$check['table']}.{$check['column']}: {$count} duplicated values.";
            }
        }

        if (Schema::hasTable('payrolls') && Schema::hasTable('expenses') && Schema::hasColumn('expenses', 'payroll_id')) {
            $missingPayrollExpenses = Payroll::query()->where('status', 'paid')->whereDoesntHave('expense')->count();
            if ($missingPayrollExpenses > 0) {
                $failures[] = "Paid payroll rows without linked cashbook expense: {$missingPayrollExpenses}. Run payroll:audit-reconciliation.";
            }
        }

        // Data-integrity checks for seat and locker allocation conflicts.
        if (Schema::hasTable('seat_allocations')) {
            $badSeatLinks = DB::table('seat_allocations as a')
                ->join('seats as s', 's.id', '=', 'a.seat_id')
                ->join('students as st', 'st.id', '=', 'a.student_id')
                ->join('study_halls as h', 'h.id', '=', 's.study_hall_id')
                ->whereColumn('h.branch_id', '<>', 'st.branch_id')
                ->count();
            if ($badSeatLinks > 0) {
                $failures[] = "Cross-branch seat allocations detected: {$badSeatLinks}.";
            }
        }

        if (Schema::hasTable('locker_allocations')) {
            $badLockerLinks = DB::table('locker_allocations as a')
                ->join('lockers as l', 'l.id', '=', 'a.locker_id')
                ->join('students as st', 'st.id', '=', 'a.student_id')
                ->whereColumn('l.branch_id', '<>', 'st.branch_id')
                ->count();
            if ($badLockerLinks > 0) {
                $failures[] = "Cross-branch locker allocations detected: {$badLockerLinks}.";
            }
        }

        return $this->finish($failures);
    }

    private function finish(array $failures): int
    {
        if ($failures === []) {
            $this->info('Release preflight passed for runtime, migrations, core business tables, finance and seat/locker integrity.');
            return self::SUCCESS;
        }

        foreach ($failures as $failure) {
            $this->error($failure);
        }
        $this->error('Release preflight failed. Do not treat the library project as final until all failures are resolved.');
        return self::FAILURE;
    }
}
