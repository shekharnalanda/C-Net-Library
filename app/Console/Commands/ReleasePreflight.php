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

        if (config('app.timezone') !== 'Asia/Kolkata') {
            $failures[] = 'APP timezone must be Asia/Kolkata for date-based memberships, attendance and scheduler behavior.';
        }

        if (config('app.env') !== 'production') {
            $failures[] = 'APP_ENV must resolve to production.';
        }

        if ((bool) config('app.debug')) {
            $failures[] = 'APP_DEBUG must be false in production.';
        }

        $appUrl = (string) config('app.url');
        if (! str_starts_with(strtolower($appUrl), 'https://')) {
            $failures[] = 'APP_URL must use HTTPS.';
        }

        if (config('session.driver') !== 'database') {
            $failures[] = 'SESSION_DRIVER must resolve to database for the production runtime design.';
        }

        if (! (bool) config('session.secure')) {
            $failures[] = 'SESSION_SECURE_COOKIE must resolve to true.';
        }

        if (config('cache.default') !== 'database') {
            $failures[] = 'CACHE_STORE must resolve to database for scheduler locks and runtime consistency.';
        }

        if (config('queue.default') !== 'database') {
            $failures[] = 'QUEUE_CONNECTION must resolve to database for the production runtime design.';
        }

        foreach (['sessions', 'cache', 'cache_locks', 'jobs', 'job_batches', 'failed_jobs'] as $table) {
            if (! Schema::hasTable($table)) {
                $failures[] = "Required runtime table is missing: {$table}.";
            }
        }

        $duplicateChecks = [
            'payments.transaction_ref' => Payment::query()
                ->whereNotNull('transaction_ref')
                ->where('transaction_ref', '<>', '')
                ->select('transaction_ref')
                ->groupBy('transaction_ref')
                ->havingRaw('COUNT(*) > 1')
                ->count(),
            'expenses.transaction_ref' => Expense::query()
                ->whereNotNull('transaction_ref')
                ->where('transaction_ref', '<>', '')
                ->select('transaction_ref')
                ->groupBy('transaction_ref')
                ->havingRaw('COUNT(*) > 1')
                ->count(),
            'payrolls.transaction_ref' => Payroll::query()
                ->whereNotNull('transaction_ref')
                ->where('transaction_ref', '<>', '')
                ->select('transaction_ref')
                ->groupBy('transaction_ref')
                ->havingRaw('COUNT(*) > 1')
                ->count(),
            'library_charge_payments.transaction_ref' => LibraryChargePayment::query()
                ->whereNotNull('transaction_ref')
                ->where('transaction_ref', '<>', '')
                ->select('transaction_ref')
                ->groupBy('transaction_ref')
                ->havingRaw('COUNT(*) > 1')
                ->count(),
        ];

        foreach ($duplicateChecks as $field => $count) {
            if ($count > 0) {
                $failures[] = "Duplicate non-empty transaction references found in {$field}: {$count} duplicated values.";
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
        }

        try {
            DB::connection()->getPdo();
            $this->line('Database connection: OK');
        } catch (\Throwable $exception) {
            $failures[] = 'Database connection failed: '.$exception->getMessage();
        }

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
