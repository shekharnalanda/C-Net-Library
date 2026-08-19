<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Payroll;
use App\Models\Staff;
use App\Models\StaffAttendance;
use App\Models\StaffLeave;
use App\Models\StaffShift;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class StaffController extends Controller
{
    public function index(Request $request): View
    {
        $staff = Staff::with('branch')->latest()->paginate(25);
        $shifts = StaffShift::where('status', true)->orderBy('name')->get();
        $branches = Branch::where('status', true)->orderBy('name')->get();
        $todayAttendance = StaffAttendance::with('staff')->whereDate('attendance_date', today())->latest()->get();
        $pendingLeaves = StaffLeave::with('staff')->where('status', 'pending')->latest()->get();
        $recentPayrolls = Payroll::with('staff')->latest()->limit(15)->get();

        return view('admin.staff.index', compact(
            'staff', 'shifts', 'branches', 'todayAttendance', 'pendingLeaves', 'recentPayrolls'
        ));
    }

    public function store(Request $request): RedirectResponse
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

        do {
            $code = 'CNL-STF-' . strtoupper(Str::random(6));
        } while (Staff::where('staff_code', $code)->exists());

        Staff::create($data + [
            'staff_code' => $code,
            'monthly_salary' => $data['monthly_salary'] ?? 0,
            'status' => 'active',
        ]);

        return back()->with('success', 'Staff member created.');
    }

    public function attendance(Request $request, Staff $staff): RedirectResponse
    {
        $data = $request->validate([
            'staff_shift_id' => ['nullable', 'exists:staff_shifts,id'],
            'status' => ['required', 'in:present,absent,half_day,leave'],
            'remarks' => ['nullable', 'string', 'max:1000'],
        ]);

        StaffAttendance::updateOrCreate(
            ['staff_id' => $staff->id, 'attendance_date' => today()->toDateString()],
            $data + [
                'check_in' => $data['status'] === 'present' ? now() : null,
            ]
        );

        return back()->with('success', 'Staff attendance updated.');
    }

    public function leave(Request $request, StaffLeave $staffLeave): RedirectResponse
    {
        $data = $request->validate([
            'status' => ['required', 'in:approved,rejected'],
            'admin_remarks' => ['nullable', 'string', 'max:1000'],
        ]);

        $staffLeave->update($data + ['approved_by' => auth()->id()]);

        return back()->with('success', 'Leave request updated.');
    }

    public function payroll(Request $request, Staff $staff): RedirectResponse
    {
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

        Payroll::updateOrCreate(
            ['staff_id' => $staff->id, 'month' => $data['month'], 'year' => $data['year']],
            [
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
            ]
        );

        return back()->with('success', 'Payroll record saved.');
    }
}
