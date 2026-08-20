<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Payroll;
use App\Models\Staff;
use App\Models\StaffAttendance;
use App\Models\StaffLeave;
use App\Models\StaffShift;
use App\Services\AdminBranchScope;
use App\Services\AuditService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class StaffController extends Controller
{
    public function index(Request $request, AdminBranchScope $branchScope): View
    {
        $staffQuery = $branchScope->apply(Staff::query(), $request->user());
        $staff = (clone $staffQuery)->with('branch')->latest()->paginate(25);
        $staffIds = (clone $staffQuery)->pluck('id');

        $shifts = StaffShift::where('status', true)->orderBy('name')->get();
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
            'payment_mode' => ['nullable', 'string', 'max:50'],
            'transaction_ref' => ['nullable', 'string', 'max:150'],
            'remarks' => ['nullable', 'string', 'max:1000'],
        ]);

        $basic = (float) $staff->monthly_salary;
        $allowances = (float) ($data['allowances'] ?? 0);
        $deductions = (float) ($data['deductions'] ?? 0);

        $payroll = Payroll::firstOrNew([
            'staff_id' => $staff->id,
            'month' => $data['month'],
            'year' => $data['year'],
        ]);
        $old = $payroll->exists ? $payroll->only([
            'basic_salary', 'allowances', 'deductions', 'net_salary', 'status', 'paid_on', 'payment_mode', 'transaction_ref', 'remarks',
        ]) : [];

        $payroll->fill([
            'basic_salary' => $basic,
            'allowances' => $allowances,
            'deductions' => $deductions,
            'net_salary' => max(0, $basic + $allowances - $deductions),
            'status' => $data['status'],
            'paid_on' => $data['status'] === 'paid' ? today() : null,
            'payment_mode' => $data['payment_mode'] ?? null,
            'transaction_ref' => $data['transaction_ref'] ?? null,
            'processed_by' => auth()->id(),
            'remarks' => $data['remarks'] ?? null,
        ])->save();

        $audit->log('staff.payroll.saved', $payroll, $old, $payroll->only([
            'staff_id', 'month', 'year', 'basic_salary', 'allowances', 'deductions', 'net_salary', 'status', 'paid_on', 'payment_mode', 'transaction_ref', 'processed_by', 'remarks',
        ]));

        return back()->with('success', 'Payroll record saved.');
    }

    private function assertStaffBranch(Request $request, Staff $staff): void
    {
        abort_unless(
            $request->user()->isGlobalAdmin()
            || (int) $staff->branch_id === (int) $request->user()->branch_id,
            403
        );
    }
}
