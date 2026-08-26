<?php

namespace App\Console\Commands;

use App\Models\Student;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;

class ResetStudentSeatData extends Command
{
    protected $signature = 'maintenance:reset-students-seats {--confirm=}';
    protected $description = 'Back up and remove all student/admission operational data and all seats while preserving branches, halls, slots, plans, lockers and library inventory.';

    public function handle(): int
    {
        if ($this->option('confirm') !== 'RESET-CNET-LIBRARY') {
            $this->error('Refusing destructive reset. Re-run with --confirm=RESET-CNET-LIBRARY');
            return self::FAILURE;
        }

        $studentIds = Student::query()->pluck('id');
        $userIds = Student::query()->whereNotNull('user_id')->pluck('user_id');
        $membershipIds = $this->ids('student_memberships', 'student_id', $studentIds);
        $paymentIds = $this->ids('payments', 'student_id', $studentIds);
        $bookIssueIds = $this->ids('book_issues', 'student_id', $studentIds);
        $lockerAllocationIds = $this->ids('locker_allocations', 'student_id', $studentIds);

        $backupTables = [
            'admissions', 'students', 'student_memberships', 'payments', 'payment_adjustments',
            'attendances', 'seat_allocations', 'seats', 'book_issues', 'book_reservations',
            'library_charge_payments', 'saved_jobs', 'job_clicks', 'communication_logs',
            'digital_resource_logs', 'locker_allocations', 'locker_payments', 'users',
        ];

        $backup = [
            'created_at' => now()->toIso8601String(),
            'student_ids' => $studentIds->values()->all(),
            'user_ids' => $userIds->values()->all(),
            'tables' => [],
        ];

        foreach ($backupTables as $table) {
            if (! Schema::hasTable($table)) continue;

            $query = DB::table($table);
            if ($table === 'admissions' || $table === 'seats') {
                $rows = $query->get();
            } elseif ($table === 'users') {
                $rows = $userIds->isEmpty() ? collect() : $query->whereIn('id', $userIds)->get();
            } elseif ($table === 'payment_adjustments') {
                $rows = $paymentIds->isEmpty() ? collect() : $query->whereIn('payment_id', $paymentIds)->get();
            } elseif ($table === 'library_charge_payments') {
                $rows = $bookIssueIds->isEmpty() ? collect() : $query->whereIn('book_issue_id', $bookIssueIds)->get();
            } elseif ($table === 'locker_payments') {
                $rows = $lockerAllocationIds->isEmpty() ? collect() : $query->whereIn('locker_allocation_id', $lockerAllocationIds)->get();
            } elseif (Schema::hasColumn($table, 'student_id')) {
                $rows = $studentIds->isEmpty() ? collect() : $query->whereIn('student_id', $studentIds)->get();
            } elseif ($table === 'student_memberships') {
                $rows = $membershipIds->isEmpty() ? collect() : $query->whereIn('id', $membershipIds)->get();
            } else {
                $rows = collect();
            }
            $backup['tables'][$table] = $rows->map(fn ($row) => (array) $row)->all();
        }

        $dir = storage_path('app/maintenance-backups');
        File::ensureDirectoryExists($dir);
        $backupPath = $dir.'/student-seat-reset-'.now()->format('Ymd-His').'.json';
        File::put($backupPath, json_encode($backup, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

        DB::transaction(function () use ($studentIds, $userIds, $paymentIds, $bookIssueIds, $lockerAllocationIds): void {
            $this->deleteWhereIn('payment_adjustments', 'payment_id', $paymentIds);
            $this->deleteWhereIn('library_charge_payments', 'book_issue_id', $bookIssueIds);
            $this->deleteWhereIn('locker_payments', 'locker_allocation_id', $lockerAllocationIds);

            foreach ([
                'saved_jobs', 'job_clicks', 'communication_logs', 'digital_resource_logs',
                'book_reservations', 'book_issues', 'locker_allocations', 'payments',
                'attendances', 'student_memberships',
            ] as $table) {
                $this->deleteWhereIn($table, 'student_id', $studentIds);
            }

            if (Schema::hasTable('seat_allocations')) DB::table('seat_allocations')->delete();
            if (Schema::hasTable('seats')) DB::table('seats')->delete();
            if (Schema::hasTable('admissions')) DB::table('admissions')->delete();
            if (Schema::hasTable('students')) DB::table('students')->delete();

            if (! $userIds->isEmpty() && Schema::hasTable('users')) {
                DB::table('users')->whereIn('id', $userIds)->where('role', 'student')->delete();
            }

            if (Schema::hasTable('book_copies')) {
                DB::table('book_copies')->whereIn('status', ['issued', 'reserved'])->update(['status' => 'available', 'updated_at' => now()]);
            }
        }, 3);

        $this->info('RESET_COMPLETED');
        $this->line('BACKUP='.$backupPath);
        $this->line('STUDENTS='.DB::table('students')->count());
        $this->line('ADMISSIONS='.DB::table('admissions')->count());
        $this->line('SEATS='.DB::table('seats')->count());
        $this->line('SEAT_ALLOCATIONS='.DB::table('seat_allocations')->count());

        return self::SUCCESS;
    }

    private function ids(string $table, string $column, $ids)
    {
        if (! Schema::hasTable($table) || $ids->isEmpty()) return collect();
        return DB::table($table)->whereIn($column, $ids)->pluck('id');
    }

    private function deleteWhereIn(string $table, string $column, $ids): void
    {
        if (! Schema::hasTable($table) || ! Schema::hasColumn($table, $column) || $ids->isEmpty()) return;
        DB::table($table)->whereIn($column, $ids)->delete();
    }
}
