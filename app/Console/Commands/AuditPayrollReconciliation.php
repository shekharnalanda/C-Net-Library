<?php

namespace App\Console\Commands;

use App\Models\Expense;
use App\Models\Payroll;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class AuditPayrollReconciliation extends Command
{
    protected $signature = 'payroll:audit-reconciliation';

    protected $description = 'Report payroll transaction-reference duplicates and paid payrolls missing or mismatching linked cashbook expenses.';

    public function handle(): int
    {
        $issues = 0;

        $duplicates = Payroll::query()
            ->select('transaction_ref', DB::raw('COUNT(*) as aggregate'))
            ->whereNotNull('transaction_ref')
            ->where('transaction_ref', '<>', '')
            ->groupBy('transaction_ref')
            ->havingRaw('COUNT(*) > 1')
            ->orderBy('transaction_ref')
            ->get();

        foreach ($duplicates as $duplicate) {
            $issues++;
            $this->error(sprintf(
                'Duplicate payroll transaction ref: %s (%d records)',
                $duplicate->transaction_ref,
                $duplicate->aggregate,
            ));
        }

        Payroll::query()
            ->with('staff')
            ->where('status', 'paid')
            ->orderBy('id')
            ->chunkById(100, function ($payrolls) use (&$issues) {
                foreach ($payrolls as $payroll) {
                    $expense = Expense::query()->where('payroll_id', $payroll->id)->first();

                    if (! $expense) {
                        $issues++;
                        $this->error(sprintf(
                            'Paid payroll #%d (%s, %02d/%d) has no linked cashbook expense.',
                            $payroll->id,
                            $payroll->staff?->staff_code ?? 'unknown staff',
                            $payroll->month,
                            $payroll->year,
                        ));
                        continue;
                    }

                    $mismatches = [];
                    if ((int) $expense->branch_id !== (int) $payroll->staff?->branch_id) {
                        $mismatches[] = 'branch';
                    }
                    if (round((float) $expense->amount, 2) !== round((float) $payroll->net_salary, 2)) {
                        $mismatches[] = 'amount';
                    }
                    if ($payroll->paid_on && $expense->expense_date?->toDateString() !== $payroll->paid_on->toDateString()) {
                        $mismatches[] = 'date';
                    }
                    if ($expense->category !== 'Salary') {
                        $mismatches[] = 'category';
                    }
                    if (($expense->transaction_ref ?: null) !== ($payroll->transaction_ref ?: null)) {
                        $mismatches[] = 'transaction_ref';
                    }

                    if ($mismatches !== []) {
                        $issues++;
                        $this->error(sprintf(
                            'Payroll #%d / expense #%d mismatch: %s.',
                            $payroll->id,
                            $expense->id,
                            implode(', ', $mismatches),
                        ));
                    }
                }
            });

        if ($issues === 0) {
            $this->info('Payroll/cashbook reconciliation audit passed with no detected issues.');

            return self::SUCCESS;
        }

        $this->warn(sprintf('%d payroll/cashbook reconciliation issue(s) detected. No data was changed.', $issues));

        return self::FAILURE;
    }
}
