<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Staff Management - C-Net Library</title>
    <style>
        body{font-family:Arial,sans-serif;background:#f5f7fb;margin:0;color:#1f2937}.wrap{max-width:1250px;margin:30px auto;padding:0 18px}.top{display:flex;justify-content:space-between;align-items:center;gap:12px;margin-bottom:18px}.grid{display:grid;grid-template-columns:1fr 2fr;gap:18px}.card{background:#fff;border:1px solid #e5e7eb;border-radius:14px;padding:18px;margin-bottom:18px;box-shadow:0 6px 22px rgba(15,23,42,.05)}.table{width:100%;border-collapse:collapse}.table th,.table td{padding:9px;border-bottom:1px solid #edf0f4;text-align:left;font-size:13px;vertical-align:top}.btn{display:inline-block;padding:9px 12px;border:0;border-radius:8px;background:#111827;color:#fff;text-decoration:none;cursor:pointer}.btn.alt{background:#fff;color:#111827;border:1px solid #d1d5db}.muted{color:#6b7280}input,select,textarea{width:100%;box-sizing:border-box;padding:9px;border:1px solid #d1d5db;border-radius:8px;margin-top:4px}.field{margin-bottom:10px}.inline{display:flex;gap:8px;align-items:center;flex-wrap:wrap}.inline input,.inline select{width:auto;min-width:100px;margin:0}.payroll-form{display:grid;grid-template-columns:64px 82px 92px 92px 105px 110px 130px auto;gap:6px;align-items:end}.payroll-form input,.payroll-form select{margin:0}.status-pill{display:inline-block;padding:4px 8px;border-radius:999px;background:#eef2ff;font-size:12px}@media(max-width:1050px){.payroll-form{grid-template-columns:repeat(2,minmax(120px,1fr))}}@media(max-width:900px){.grid{grid-template-columns:1fr}.top{align-items:flex-start;flex-direction:column}}@media(max-width:600px){.payroll-form{grid-template-columns:1fr}}
    </style>
</head>
<body>
<div class="wrap">
    <div class="top">
        <div><h1 style="margin:0">HR / Staff Management</h1><div class="muted">Staff, attendance, leave and payroll</div></div>
        <div class="inline"><a class="btn alt" href="{{ route('admin.expenses.index') }}">Cashbook</a><a class="btn" href="{{ route('admin.dashboard') }}">Dashboard</a></div>
    </div>

    @if(session('success'))<div class="card" style="background:#f0fdf4;border-color:#86efac">{{ session('success') }}</div>@endif
    @if($errors->any())<div class="card" style="background:#fef2f2;border-color:#fca5a5"><ul style="margin:0;padding-left:18px">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif

    <div class="grid">
        <div>
            <div class="card">
                <h2 style="margin-top:0">Add Staff</h2>
                <form method="POST" action="{{ route('admin.staff.store') }}">
                    @csrf
                    <div class="field"><label>Name<input name="name" required value="{{ old('name') }}"></label></div>
                    <div class="field"><label>Role<input name="role" required value="{{ old('role','staff') }}"></label></div>
                    <div class="field"><label>Branch<select name="branch_id" required>@foreach($branches as $branch)<option value="{{ $branch->id }}" @selected(old('branch_id', auth()->user()->scopedBranchId()) == $branch->id)>{{ $branch->name }}</option>@endforeach</select></label></div>
                    <div class="field"><label>Mobile<input name="mobile" value="{{ old('mobile') }}"></label></div>
                    <div class="field"><label>Email<input type="email" name="email" value="{{ old('email') }}"></label></div>
                    <div class="field"><label>Joining Date<input type="date" name="joining_date" value="{{ old('joining_date') }}"></label></div>
                    <div class="field"><label>Monthly Salary<input type="number" step="0.01" min="0" name="monthly_salary" value="{{ old('monthly_salary',0) }}"></label></div>
                    <button class="btn" type="submit">Create Staff</button>
                </form>
            </div>

            <div class="card">
                <h2 style="margin-top:0">Pending Leaves</h2>
                @forelse($pendingLeaves as $leave)
                    <div style="padding:10px 0;border-bottom:1px solid #edf0f4">
                        <strong>{{ $leave->staff?->name }}</strong><br>
                        <span class="muted">{{ $leave->from_date?->format('d M Y') }} - {{ $leave->to_date?->format('d M Y') }} · {{ ucfirst($leave->leave_type) }}</span>
                        <form class="inline" method="POST" action="{{ route('admin.staff.leaves.update',$leave) }}" style="margin-top:8px">
                            @csrf @method('PATCH')
                            <select name="status"><option value="approved">Approve</option><option value="rejected">Reject</option></select>
                            <input name="admin_remarks" placeholder="Remarks">
                            <button class="btn" type="submit">Save</button>
                        </form>
                    </div>
                @empty<div class="muted">No pending leave requests.</div>@endforelse
            </div>
        </div>

        <div>
            <div class="card">
                <h2 style="margin-top:0">Staff Directory</h2>
                <div style="overflow:auto"><table class="table">
                    <thead><tr><th>Code</th><th>Name</th><th>Role</th><th>Branch</th><th>Salary</th><th>Attendance Today</th></tr></thead>
                    <tbody>
                    @forelse($staff as $member)
                        <tr>
                            <td>{{ $member->staff_code }}</td><td><strong>{{ $member->name }}</strong><br><span class="muted">{{ $member->mobile ?: $member->email }}</span></td><td>{{ ucfirst($member->role) }}</td><td>{{ $member->branch?->name ?? '—' }}</td><td>₹{{ number_format((float)$member->monthly_salary,2) }}</td>
                            <td>
                                <form class="inline" method="POST" action="{{ route('admin.staff.attendance',$member) }}">@csrf
                                    <select name="status"><option value="present">Present</option><option value="absent">Absent</option><option value="half_day">Half day</option><option value="leave">Leave</option></select>
                                    <select name="staff_shift_id"><option value="">No shift</option>@foreach($shifts as $shift)<option value="{{ $shift->id }}">{{ $shift->name }}</option>@endforeach</select>
                                    <button class="btn" type="submit">Mark</button>
                                </form>
                            </td>
                        </tr>
                        <tr>
                            <td colspan="6" style="background:#f8fafc">
                                <form class="payroll-form" method="POST" action="{{ route('admin.staff.payroll',$member) }}">@csrf
                                    <label>Month<input type="number" name="month" min="1" max="12" value="{{ now()->month }}" required></label>
                                    <label>Year<input type="number" name="year" value="{{ now()->year }}" required></label>
                                    <label>Allowance<input type="number" name="allowances" min="0" step="0.01" value="0"></label>
                                    <label>Deduction<input type="number" name="deductions" min="0" step="0.01" value="0"></label>
                                    <label>Status<select name="status"><option value="pending">Pending</option><option value="paid">Paid</option></select></label>
                                    <label>Mode<select name="payment_mode"><option value="">Select</option><option value="cash">Cash</option><option value="upi">UPI</option><option value="card">Card</option><option value="bank_transfer">Bank</option><option value="other">Other</option></select></label>
                                    <label>Txn Ref<input type="text" name="transaction_ref" placeholder="Optional"></label>
                                    <button class="btn" type="submit">Save Payroll</button>
                                </form>
                                <div class="muted" style="margin-top:6px">When marked Paid, payroll is automatically posted to Cashbook and becomes immutable.</div>
                            </td>
                        </tr>
                    @empty<tr><td colspan="6" class="muted">No staff records.</td></tr>@endforelse
                    </tbody>
                </table></div>
                <div style="margin-top:12px">{{ $staff->links() }}</div>
            </div>

            <div class="card">
                <h2 style="margin-top:0">Today's Attendance</h2>
                <div style="overflow:auto"><table class="table"><thead><tr><th>Staff</th><th>Status</th><th>Check-in</th><th>Worked</th></tr></thead><tbody>
                @forelse($todayAttendance as $row)<tr><td>{{ $row->staff?->name }}</td><td><span class="status-pill">{{ ucfirst(str_replace('_',' ',$row->status)) }}</span></td><td>{{ $row->check_in?->format('h:i A') ?? '—' }}</td><td>{{ $row->worked_minutes }} min</td></tr>@empty<tr><td colspan="4" class="muted">No attendance marked today.</td></tr>@endforelse
                </tbody></table></div>
            </div>

            <div class="card">
                <h2 style="margin-top:0">Recent Payroll</h2>
                <div style="overflow:auto"><table class="table"><thead><tr><th>Staff</th><th>Period</th><th>Net Salary</th><th>Status</th></tr></thead><tbody>
                @forelse($recentPayrolls as $payroll)<tr><td>{{ $payroll->staff?->name }}</td><td>{{ str_pad($payroll->month,2,'0',STR_PAD_LEFT) }}/{{ $payroll->year }}</td><td>₹{{ number_format((float)$payroll->net_salary,2) }}</td><td><span class="status-pill">{{ ucfirst($payroll->status) }}</span></td></tr>@empty<tr><td colspan="4" class="muted">No payroll records.</td></tr>@endforelse
                </tbody></table></div>
            </div>
        </div>
    </div>
</div>
</body>
</html>
