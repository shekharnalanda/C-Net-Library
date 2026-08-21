<?php

namespace App\Console\Commands;

use App\Models\Expense;
use App\Models\LibraryChargePayment;
use App\Models\Payment;
use App\Models\Payroll;
use Illuminate\Console\Command;
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

        foreach (['sessions', 'cache', 'cache_locks', 'jobs', 'job_batches', 'failed_jobs'] as $table) {
            if (! Schema::hasTable($table)) {
                $failures[] = "Required runtime table is missing: {$table}.";
            }
        }

        $businessTables = ['payments', 'expenses', 'payrolls'];
        foreach ($businessTables as $table) {
            if (! Schema::hasTable($table)) {
                $failures[] = "Required business table is missing: {$table}. Migration state is incomplete.";
            }
        }

        $duplicateChecks = [
            ['table' => 'payments', 'column' => 'transaction_ref', 'model' => Payment::class],
            ['table' => 'expenses', 'column' => 'transaction_ref', 'model' => Expense::class],
            ['table' => 'payrolls', 'column' => 'transaction_ref', 'model' => Payroll::class],
            ['table' => 'library_charge_payments', 'column' => 'transaction_ref', 'model' => LibraryChargePayment::class],
        ];

        foreach ($duplicateChecks as $check) {
            if (! Schema::hasTable($check['table']) || ! Schema::hasColumn($check['table'], $check['column'])) {
                $this->warn("Skipping duplicate-reference check for {$check['table']}.{$check['column']} because the table/column is not present yet.");
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
            $missingPayrollExpenses = Payroll::query()
                ->where('status', 'paid')
                ->whereDoesntHave('expense')
                ->count();

            if ($missingPayrollExpenses > 0) {
                $failures[] = "Paid payroll rows without linked cashbook expense: {$missingPayrollExpenses}. Run payroll:audit-reconciliation.";
            }
        } else {
            $this->warn('Skipping payroll-to-cashbook linkage check because the payroll linkage migration has not been applied yet.');
        }

        return $this->finish($failures);
    }

    private function finish(array $failures): int
    {
        if ($failures === []) {
            $this->info('Release preflight passed. This does not replace CI, schedule:list verification, or a verified backup/restore drill.');

            return self::SUCCESS;
        }

        foreach ($failures as $failure) {
            $this->error($failure);
        }

        $this->error('Release preflight failed. Do not deploy until all failures are resolved.');

        return self::FAILURE;
    }
}
