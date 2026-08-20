<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Expense;
use App\Models\Payroll;
use App\Models\Staff;
use App\Models\StaffAttendance;
use App\Models\StaffLeave;
use App\Models\StaffShift;
use App\Services\AdminBranchScope;
use App\Services\AuditService;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class StaffController extends Controller
{
    public function index(Request $request, AdminBranchScope $branchScope): View
    {
        $staffQuery = $branchScope->apply(Staff::query(), $request->user());
        $staff = (clone $staffQuery)->with('branch')->latest()->paginate(25);
        $staffIds = (clone $staffQuery)->pluck('id');

        $shifts = StaffShift::where('status', true)
            ->when(! $request->user()->isGlobalAdmin(), function ($query) use ($request) {
                $query->where(function ($sub) use ($request) {
                    $sub->whereNull('branch_id')->orWhere('branch_id', $request->user()->branch_id);
                });
            })
            ->orderBy('name')
            ->get();
        $branches = Branch::where('status', true)
            ->when(! $request->user()->isGlobalAdmin(), fn ($query) => $query->whereKey($request->user()->branch_id))
            ->orderBy('name')
            ->get();
        $todayAttendance = StaffAttendance::with('staff')
            ->whereIn('staff_id', $staffIds)
            ->whereDate('attendance_date', today())
            ->latest()
            ->get();
        $pendingLeaves = StaffLeave::with('staff')
            ->whereIn('staff_id', $staffIds)
            ->where('status', 'pending')
            ->latest()
            ->get();
        $recentPayrolls = Payroll::with('staff')
            ->whereIn('staff_id', $staffIds)
            ->latest()
            ->limit(15)
            ->get();

        return view('admin.staff.index', compact(
            'staff', 'shifts', 'branches', 'todayAttendance', 'pendingLeaves', 'recentPayrolls'
        ));
    }

    public function store(Request $request, AuditService $audit): RedirectResponse
    {
        $data = $request->validate([
            'branch_id' => ['nullable', 'exists:branches,id'],
            'name' => ['required', 'string', 'max:150'],
            'role' => ['required', 'string', 'max:100'],
            'mobile' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:150'],
            'joining_date' => ['nullable', 'date'],
            'monthly_salary' => ['nullable', 'numeric', 'min:0'],
        ]);

        if (! $request->user()->isGlobalAdmin()) {
            $data['branch_id'] = $request->user()->branch_id;
        }

        do {
            $code = 'CNL-STF-' . strtoupper(Str::random(6));
        } while (Staff::where('staff_code', $code)->exists());

        $staff = Staff::create($data + [
            'staff_code' => $code,
            'monthly_salary' => $data['monthly_salary'] ?? 0,
            'status' => 'active',
        ]);

        $audit->log('staff.created', $staff, [], $staff->only([
            'branch_id', 'staff_code', 'name', 'role', 'joining_date', 'monthly_salary', 'status',
        ]));

        return back()->with('success', 'Staff member created.');
    }

    public function attendance(Request $request, Staff $staff, AuditService $audit): RedirectResponse
    {
        $this->assertStaffBranch($request, $staff);

        $data = $request->validate([
            'staff_shift_id' => ['nullable', 'exists:staff_shifts,id'],
            'status' => ['required', 'in:present,absent,half_day,leave'],
            'remarks' => ['nullable', 'string', 'max:1000'],
        ]);

        if (! empty($data['staff_shift_id'])) {
            $shift = StaffShift::findOrFail($data['staff_shift_id']);
            if ($shift->branch_id !== null && (int) $shift->branch_id !== (int) $staff->branch_id) {
                throw ValidationException::withMessages([
                    'staff_shift_id' => 'Selected shift does not belong to this staff branch.',
                ]);
            }
        }

        $attendance = StaffAttendance::firstOrNew([
            'staff_id' => $staff->id,
            'attendance_date' => today()->toDateString(),
        ]);
        $old = $attendance->exists ? $attendance->only(['staff_shift_id', 'status', 'check_in', 'check_out', 'remarks']) : [];

        $attendance->fill($data + [
            'check_in' => $data['status'] === 'present' ? now() : null,
        ])->save();

        $audit->log('staff.attendance.updated', $attendance, $old, $attendance->only([
            'staff_id', 'attendance_date', 'staff_shift_id', 'status', 'check_in', 'check_out', 'remarks',
        ]));

        return back()->with('success', 'Staff attendance updated.');
    }

    public function leave(Request $request, StaffLeave $staffLeave, AuditService $audit): RedirectResponse
    {
        $staffLeave->loadMissing('staff');
        $this->assertStaffBranch($request, $staffLeave->staff);

        $data = $request->validate([
            'status' => ['required', 'in:approved,rejected'],
            'admin_remarks' => ['nullable', 'string', 'max:1000'],
        ]);

        $old = $staffLeave->only(['status', 'admin_remarks', 'approved_by']);
        $staffLeave->update($data + ['approved_by' => auth()->id()]);
        $staffLeave->refresh();

        $audit->log('staff.leave.reviewed', $staffLeave, $old, $staffLeave->only([
            'status', 'admin_remarks', 'approved_by',
        ]));

        return back()->with('success', 'Leave request updated.');
    }

    public function payroll(Request $request, Staff $staff, AuditService $audit): RedirectResponse
    {
        $this->assertStaffBranch($request, $staff);

        $data = $request->validate([
            'month' => ['required', 'integer', 'between:1,12'],
            'year' => ['required', 'integer', 'min:2020', 'max:2100'],
            'allowances' => ['nullable', 'numeric', 'min:0'],
            'deductions' => ['nullable', 'numeric', 'min:0'],
            'status' => ['required', 'in:pending,paid'],
            'payment_mode' => ['nullable', 'in:cash,upi,card,bank_transfer,other'],
            'transaction_ref' => ['nullable', 'string', 'max:150'],
            'remarks' => ['nullable', 'string', 'max:1000'],
        ]);

        $transactionRef = trim((string) ($data['transaction_ref'] ?? ''));
        if ($transactionRef !== '' && Payroll::query()->where('transaction_ref', $transactionRef)->exists()) {
            throw ValidationException::withMessages([
                'transaction_ref' => 'This payroll transaction reference has already been recorded.',
            ]);
        }

        try {
            $result = DB::transaction(function () use ($staff, $data, $transactionRef) {
                $payroll = Payroll::query()
                    ->where('staff_id', $staff->id)
                    ->where('month', $data['month'])
                    ->where('year', $data['year'])
                    ->lockForUpdate()
                    ->first();

                $old = $payroll?->only([
                    'basic_salary', 'allowances', 'deductions', 'net_salary', 'status', 'paid_on', 'payment_mode', 'transaction_ref', 'remarks',
                ]) ?? [];

                if ($payroll?->status === 'paid') {
                    throw ValidationException::withMessages([
                        'status' => 'Paid payroll is immutable. Use a cashbook adjustment for any financial correction.',
                    ]);
                }

                if ($transactionRef !== '' && Payroll::query()
                    ->where('transaction_ref', $transactionRef)
                    ->when($payroll, fn ($query) => $query->whereKeyNot($payroll->id))
                    ->exists()) {
                    throw ValidationException::withMessages([
                        'transaction_ref' => 'This payroll transaction reference has already been recorded.',
                    ]);
                }

                $basic = (float) $staff->monthly_salary;
                $allowances = (float) ($data['allowances'] ?? 0);
                $deductions = (float) ($data['deductions'] ?? 0);
                $net = max(0, $basic + $allowances - $deductions);

                $payroll ??= new Payroll([
                    'staff_id' => $staff->id,
                    'month' => $data['month'],
                    'year' => $data['year'],
                ]);

                $payroll->fill([
                    'basic_salary' => $basic,
                    'allowances' => $allowances,
                    'deductions' => $deductions,
                    'net_salary' => $net,
                    'status' => $data['status'],
                    'paid_on' => $data['status'] === 'paid' ? today() : null,
                    'payment_mode' => $data['payment_mode'] ?? null,
                    'transaction_ref' => $transactionRef !== '' ? $transactionRef : null,
                    'processed_by' => auth()->id(),
                    'remarks' => $data['remarks'] ?? null,
                ])->save();

                if ($data['status'] === 'paid') {
                    if ($net <= 0) {
                        throw ValidationException::withMessages([
                            'status' => 'A zero-value payroll cannot be marked paid.',
                        ]);
                    }

                    Expense::firstOrCreate(
                        ['payroll_id' => $payroll->id],
                        [
                            'branch_id' => $staff->branch_id,
                            'expense_date' => $payroll->paid_on,
                            'category' => 'Salary',
                            'payee' => $staff->name,
                            'amount' => $net,
                            'payment_mode' => $data['payment_mode'] ?? 'other',
                            'transaction_ref' => $transactionRef !== '' ? $transactionRef : null,
                            'description' => sprintf('Payroll %02d/%d for %s (%s)', $payroll->month, $payroll->year, $staff->name, $staff->staff_code),
                            'created_by' => auth()->id(),
                        ]
                    );
                }

                return [$payroll->fresh(), $old];
            }, 3);
        } catch (QueryException $exception) {
            if ($this->isDuplicatePayrollTransactionReference($exception)) {
                throw ValidationException::withMessages([
                    'transaction_ref' => 'This payroll transaction reference has already been recorded.',
                ]);
            }

            throw $exception;
        }

        [$payroll, $old] = $result;

        $audit->log('staff.payroll.saved', $payroll, $old, $payroll->only([
            'staff_id', 'month', 'year', 'basic_salary', 'allowances', 'deductions', 'net_salary', 'status', 'paid_on', 'payment_mode', 'transaction_ref', 'processed_by', 'remarks',
        ]));

        return back()->with('success', $payroll->status === 'paid'
            ? 'Payroll marked paid and posted to cashbook.'
            : 'Payroll record saved.');
    }

    private function assertStaffBranch(Request $request, Staff $staff): void
    {
        abort_unless(
            $request->user()->isGlobalAdmin()
            || (int) $staff->branch_id === (int) $request->user()->branch_id,
            403
        );
    }

    private function isDuplicatePayrollTransactionReference(QueryException $exception): bool
    {
        $message = strtolower($exception->getMessage());

        return str_contains($message, 'payrolls_transaction_ref_unique')
            || str_contains($message, 'payrolls.transaction_ref')
            || (str_contains($message, 'duplicate entry') && str_contains($message, 'transaction_ref'));
    }
}
