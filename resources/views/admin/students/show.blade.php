<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $student->name }} - C-Net Library</title>
    <style>
        :root{--nav:#111827;--bg:#f5f7fb;--card:#fff;--muted:#6b7280;--line:#e5e7eb;--blue:#2563eb;--green:#047857;--red:#b91c1c;--amber:#b45309}
        *{box-sizing:border-box}body{font-family:Arial,sans-serif;background:var(--bg);margin:0;color:#1f2937}header{background:var(--nav);color:#fff;padding:14px 22px;display:flex;justify-content:space-between;align-items:center;gap:14px;flex-wrap:wrap}header a{color:#fff;text-decoration:none}.nav{display:flex;gap:8px;flex-wrap:wrap}.nav a{padding:8px 10px;border-radius:8px;background:#1f2937;font-size:13px}.wrap{max-width:1220px;margin:26px auto;padding:0 18px}.top{display:flex;justify-content:space-between;gap:16px;align-items:flex-start;margin-bottom:18px}.actions{display:flex;gap:8px;flex-wrap:wrap}.btn{display:inline-block;background:var(--nav);color:#fff;text-decoration:none;padding:10px 13px;border-radius:9px;border:0;cursor:pointer;font-weight:700}.btn.alt{background:var(--blue)}.btn.good{background:var(--green)}.btn.out{background:var(--red)}.btn.warn{background:var(--amber)}.btn.light{background:#e5e7eb;color:#111827}.stats{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:14px;margin-bottom:18px}.stat,.card{background:var(--card);border:1px solid var(--line);border-radius:14px;box-shadow:0 6px 22px rgba(15,23,42,.05)}.stat{padding:16px}.stat .k{font-size:12px;text-transform:uppercase;color:var(--muted);font-weight:700}.stat .v{font-size:22px;font-weight:800;margin-top:7px}.stat .s{font-size:12px;color:var(--muted);margin-top:5px}.grid{display:grid;grid-template-columns:2fr 1fr;gap:18px}.card{padding:18px;margin-bottom:18px}.muted{color:var(--muted)}.row{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:14px}.label{font-size:12px;color:var(--muted);text-transform:uppercase;font-weight:700}.value{font-weight:700;margin-top:3px}.table{width:100%;border-collapse:collapse}.table th,.table td{padding:11px;border-bottom:1px solid #edf0f4;text-align:left;font-size:14px}.table th{font-size:12px;text-transform:uppercase;color:var(--muted)}input,select,textarea{width:100%;padding:10px;border:1px solid #d1d5db;border-radius:9px;margin-top:5px}.field{margin-bottom:12px}.notice{padding:12px;border-radius:10px;margin-bottom:16px}.ok{background:#f0fdf4;border:1px solid #86efac}.bad{background:#fef2f2;border:1px solid #fca5a5}.pill{display:inline-block;padding:4px 8px;border-radius:999px;background:#eef2ff;font-size:12px}.section-title{display:flex;justify-content:space-between;align-items:center;gap:10px;margin-bottom:12px}.section-title h2{margin:0;font-size:18px}
        @media(max-width:900px){.stats{grid-template-columns:repeat(2,1fr)}.grid{grid-template-columns:1fr}}@media(max-width:620px){.stats{grid-template-columns:1fr}.row{grid-template-columns:1fr}.top{flex-direction:column}.wrap{padding:0 12px}}
    </style>
</head>
<body>
<header>
    <div><a href="{{ route('admin.dashboard') }}"><strong>C-Net Library</strong> — Admin</a></div>
    <div class="nav">
        <a href="{{ route('admin.dashboard') }}">Dashboard</a>
        <a href="{{ route('admin.students.index') }}">Students</a>
        <a href="{{ route('admin.admissions.index') }}">Admissions</a>
        <a href="{{ route('admin.attendance.index') }}">Attendance</a>
        <a href="{{ route('admin.expenses.index') }}">Cashbook</a>
    </div>
</header>
<div class="wrap">
    <div class="top">
        <div>
            <h1 style="margin:0 0 4px">{{ $student->name }}</h1>
            <div class="muted">{{ $student->student_code }} · {{ $student->mobile }} · {{ $student->branch?->name ?? 'No branch' }}</div>
        </div>
        <div class="actions">
            <a class="btn good" href="{{ route('admin.students.id-card', $student) }}" target="_blank" rel="noopener">Generate / Print ID Card</a>
            <a class="btn alt" href="{{ route('admin.students.renew.create', $student) }}">Renew / Change Seat</a>
            <form method="POST" action="{{ route('admin.students.rotate-qr', $student) }}" onsubmit="return confirm('Rotate this student QR? The previous QR will stop working immediately.');">
                @csrf
                <button class="btn warn" type="submit">Rotate QR</button>
            </form>
            <a class="btn light" href="{{ route('admin.students.index') }}">Back</a>
        </div>
    </div>

    @if(session('success'))<div class="notice ok">{{ session('success') }}</div>@endif
    @if($errors->any())
        <div class="notice bad"><ul style="margin:0;padding-left:18px">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
    @endif

    <div class="stats">
        <div class="stat"><div class="k">Membership</div><div class="v">{{ $activeMembership?->feePlan?->name ?? 'No active plan' }}</div><div class="s">{{ $activeMembership?->studySlot?->name ?? 'No slot' }}</div></div>
        <div class="stat"><div class="k">Fee Due</div><div class="v">₹{{ number_format($due,2) }}</div><div class="s">Paid ₹{{ number_format($paid,2) }}@if($adjusted>0) · Adj ₹{{ number_format($adjusted,2) }}@endif</div></div>
        <div class="stat"><div class="k">Current Seat</div><div class="v">{{ $allocation?->seat?->seat_no ?? '—' }}</div><div class="s">{{ $allocation?->seat?->studyHall?->name ?? 'No active seat' }}</div></div>
        <div class="stat"><div class="k">Attendance</div><div class="v">{{ $openAttendance ? 'Inside' : 'Outside' }}</div><div class="s">{{ $openAttendance?->check_in_at?->format('d M, h:i A') ?? 'Not checked in' }}</div></div>
    </div>

    <div class="grid">
        <div>
            <div class="card">
                <div class="section-title"><h2>Profile</h2><span class="pill">{{ ucfirst($student->status) }}</span></div>
                <div class="row">
                    <div><div class="label">Email</div><div class="value">{{ $student->email ?: '—' }}</div></div>
                    <div><div class="label">Joining Date</div><div class="value">{{ $student->joining_date?->format('d M Y') ?? '—' }}</div></div>
                    <div><div class="label">Guardian</div><div class="value">{{ $student->guardian_name ?: $student->father_name ?: '—' }}</div></div>
                    <div><div class="label">Branch</div><div class="value">{{ $student->branch?->name ?? '—' }}</div></div>
                </div>
            </div>

            <div class="card">
                <div class="section-title"><h2>Membership History</h2><a href="{{ route('admin.students.renew.create', $student) }}">Renew / Change Seat</a></div>
                <div style="overflow:auto">
                    <table class="table">
                        <thead><tr><th>Plan</th><th>Slot</th><th>Start</th><th>Expiry</th><th>Fee</th><th>Status</th></tr></thead>
                        <tbody>
                        @forelse($student->memberships->sortByDesc('id') as $membership)
                            <tr><td>{{ $membership->feePlan?->name ?? '—' }}</td><td>{{ $membership->studySlot?->name ?? '—' }}</td><td>{{ $membership->start_date?->format('d M Y') }}</td><td>{{ $membership->expiry_date?->format('d M Y') }}</td><td>₹{{ number_format((float)$membership->final_fee,2) }}</td><td>{{ ucfirst($membership->status) }}</td></tr>
                        @empty<tr><td colspan="6" class="muted">No membership history.</td></tr>@endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="card">
                <div class="section-title"><h2>Payment History</h2></div>
                <div style="overflow:auto">
                    <table class="table">
                        <thead><tr><th>Receipt</th><th>Date</th><th>Amount</th><th>Mode</th><th>Status</th></tr></thead>
                        <tbody>
                        @forelse($student->payments->sortByDesc('id') as $payment)
                            <tr><td><a href="{{ route('admin.payments.receipt', $payment) }}">{{ $payment->receipt_no }}</a></td><td>{{ $payment->payment_date?->format('d M Y') }}</td><td>₹{{ number_format((float)$payment->amount,2) }}</td><td>{{ strtoupper(str_replace('_',' ', $payment->payment_mode)) }}</td><td>{{ ucfirst($payment->payment_status) }}</td></tr>
                        @empty<tr><td colspan="5" class="muted">No payments recorded.</td></tr>@endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div>
            <div class="card">
                <h2 style="margin-top:0">Attendance Action</h2>
                @if($openAttendance)
                    <div class="field"><div class="label">Checked In</div><div class="value">{{ $openAttendance->check_in_at?->format('d M Y, h:i A') }}</div></div>
                    <form method="POST" action="{{ route('admin.attendance.check-out', $student) }}">@csrf<button class="btn out" type="submit">Check Out</button></form>
                @else
                    <div class="muted" style="margin-bottom:12px">Student is currently outside.</div>
                    <form method="POST" action="{{ route('admin.attendance.check-in', $student) }}">@csrf<input type="hidden" name="entry_method" value="manual"><button class="btn good" type="submit">Check In</button></form>
                @endif
                <div style="margin-top:12px"><a href="{{ route('admin.attendance.index', ['search'=>$student->student_code]) }}">View attendance history</a></div>
            </div>

            <div class="card">
                <h2 style="margin-top:0">Seat Allocation</h2>
                @if($allocation)
                    <div class="field"><div class="label">Hall / Seat</div><div class="value">{{ $allocation->seat?->studyHall?->name }} / {{ $allocation->seat?->seat_no }}</div></div>
                    <div class="field"><div class="label">Timing</div><div class="value">{{ $allocation->start_time ?? 'Flexible' }} - {{ $allocation->end_time ?? 'Flexible' }}</div></div>
                @else<div class="muted">No active seat allocation.</div>@endif
                <div style="margin-top:12px"><a href="{{ route('admin.students.renew.create', $student) }}">Change seat / membership</a></div>
            </div>

            <div class="card">
                <h2 style="margin-top:0">Collect Fee</h2>
                @if($activeMembership)
                    <div class="field"><div class="label">Total Fee</div><div class="value">₹{{ number_format((float)$activeMembership->final_fee,2) }}</div></div>
                    <div class="field"><div class="label">Paid (Net)</div><div class="value">₹{{ number_format($paid,2) }}</div></div>
                    <div class="field"><div class="label">Refunds / Adjustments</div><div class="value">₹{{ number_format($adjusted,2) }}</div></div>
                    <div class="field"><div class="label">Due</div><div class="value">₹{{ number_format($due,2) }}</div></div>
                    @if($due > 0)
                        <form method="POST" action="{{ route('admin.students.payments.store', $student) }}">
                            @csrf<input type="hidden" name="student_membership_id" value="{{ $activeMembership->id }}">
                            <div class="field"><label>Amount<input type="number" step="0.01" min="1" max="{{ $due }}" name="amount" required value="{{ old('amount',$due) }}"></label></div>
                            <div class="field"><label>Payment Mode<select name="payment_mode" required><option value="cash">Cash</option><option value="upi">UPI</option><option value="card">Card</option><option value="bank_transfer">Bank Transfer</option><option value="other">Other</option></select></label></div>
                            <div class="field"><label>Transaction Ref<input type="text" name="transaction_ref" value="{{ old('transaction_ref') }}"></label></div>
                            <div class="field"><label>Remarks<textarea name="remarks" rows="2">{{ old('remarks') }}</textarea></label></div>
                            <button class="btn" type="submit">Receive Payment</button>
                        </form>
                    @else<div class="notice ok" style="margin:0">Membership fee fully paid.</div>@endif
                @else<div class="muted">No active membership available for payment.</div>@endif
            </div>
        </div>
    </div>
</div>
</body>
</html>
