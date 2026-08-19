<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $student->name }} - C-Net Library</title>
    <style>
        body{font-family:Arial,sans-serif;background:#f5f7fb;margin:0;color:#1f2937}.wrap{max-width:1100px;margin:30px auto;padding:0 18px}.top{display:flex;justify-content:space-between;gap:16px;align-items:center;margin-bottom:20px}.actions{display:flex;gap:10px;flex-wrap:wrap}.grid{display:grid;grid-template-columns:2fr 1fr;gap:18px}.card{background:#fff;border:1px solid #e5e7eb;border-radius:14px;padding:18px;box-shadow:0 6px 22px rgba(15,23,42,.05);margin-bottom:18px}.muted{color:#6b7280}.row{display:grid;grid-template-columns:1fr 1fr;gap:12px}.label{font-size:12px;color:#6b7280;text-transform:uppercase}.value{font-weight:600;margin-top:3px}.btn{display:inline-block;background:#111827;color:#fff;text-decoration:none;padding:10px 13px;border-radius:9px;border:0;cursor:pointer}.btn.alt{background:#2563eb}.btn.good{background:#047857}.btn.out{background:#b91c1c}.table{width:100%;border-collapse:collapse}.table th,.table td{padding:10px;border-bottom:1px solid #edf0f4;text-align:left;font-size:14px}input,select,textarea{width:100%;box-sizing:border-box;padding:10px;border:1px solid #d1d5db;border-radius:9px;margin-top:5px}.field{margin-bottom:12px}@media(max-width:800px){.grid{grid-template-columns:1fr}.row{grid-template-columns:1fr}.top{align-items:flex-start;flex-direction:column}}
    </style>
</head>
<body>
<div class="wrap">
    <div class="top">
        <div>
            <h1 style="margin:0">{{ $student->name }}</h1>
            <div class="muted">{{ $student->student_code }}</div>
        </div>
        <div class="actions">
            <a class="btn alt" href="{{ route('admin.students.renew.create', $student) }}">Renew / Change Seat</a>
            <a class="btn" href="{{ route('admin.students.index') }}">Back to Students</a>
        </div>
    </div>

    @if(session('success'))
        <div class="card" style="border-color:#86efac;background:#f0fdf4">{{ session('success') }}</div>
    @endif

    @if($errors->any())
        <div class="card" style="border-color:#fca5a5;background:#fef2f2">
            <ul style="margin:0;padding-left:18px">
                @foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach
            </ul>
        </div>
    @endif

    <div class="grid">
        <div>
            <div class="card">
                <h2 style="margin-top:0">Profile</h2>
                <div class="row">
                    <div><div class="label">Mobile</div><div class="value">{{ $student->mobile }}</div></div>
                    <div><div class="label">Email</div><div class="value">{{ $student->email ?: '—' }}</div></div>
                    <div><div class="label">Branch</div><div class="value">{{ $student->branch?->name ?? '—' }}</div></div>
                    <div><div class="label">Joining Date</div><div class="value">{{ $student->joining_date?->format('d M Y') }}</div></div>
                    <div><div class="label">Guardian</div><div class="value">{{ $student->guardian_name ?: $student->father_name ?: '—' }}</div></div>
                    <div><div class="label">Status</div><div class="value">{{ ucfirst($student->status) }}</div></div>
                </div>
            </div>

            <div class="card">
                <h2 style="margin-top:0">Memberships</h2>
                <div style="overflow:auto">
                    <table class="table">
                        <thead><tr><th>Plan</th><th>Slot</th><th>Start</th><th>Expiry</th><th>Fee</th><th>Status</th></tr></thead>
                        <tbody>
                        @foreach($student->memberships->sortByDesc('id') as $membership)
                            <tr>
                                <td>{{ $membership->feePlan?->name ?? '—' }}</td>
                                <td>{{ $membership->studySlot?->name ?? '—' }}</td>
                                <td>{{ $membership->start_date?->format('d M Y') }}</td>
                                <td>{{ $membership->expiry_date?->format('d M Y') }}</td>
                                <td>₹{{ number_format((float)$membership->final_fee, 2) }}</td>
                                <td>{{ ucfirst($membership->status) }}</td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="card">
                <h2 style="margin-top:0">Payment History</h2>
                <div style="overflow:auto">
                    <table class="table">
                        <thead><tr><th>Receipt</th><th>Date</th><th>Amount</th><th>Mode</th><th>Status</th></tr></thead>
                        <tbody>
                        @forelse($student->payments->sortByDesc('id') as $payment)
                            <tr>
                                <td><a href="{{ route('admin.payments.receipt', $payment) }}">{{ $payment->receipt_no }}</a></td>
                                <td>{{ $payment->payment_date?->format('d M Y') }}</td>
                                <td>₹{{ number_format((float)$payment->amount, 2) }}</td>
                                <td>{{ strtoupper(str_replace('_',' ', $payment->payment_mode)) }}</td>
                                <td>{{ ucfirst($payment->payment_status) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="muted">No payments recorded.</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div>
            @php($activeMembership = $student->memberships->where('status','active')->sortByDesc('id')->first())
            @php($openAttendance = $student->attendances()->whereNull('check_out_at')->latest('id')->first())

            <div class="card">
                <h2 style="margin-top:0">Attendance</h2>
                @if($openAttendance)
                    <div class="field"><div class="label">Checked In</div><div class="value">{{ $openAttendance->check_in_at?->format('d M Y, h:i A') }}</div></div>
                    <form method="POST" action="{{ route('admin.attendance.check-out', $student) }}">
                        @csrf
                        <button class="btn out" type="submit">Check Out</button>
                    </form>
                @else
                    <div class="muted" style="margin-bottom:12px">Student is currently outside.</div>
                    <form method="POST" action="{{ route('admin.attendance.check-in', $student) }}">
                        @csrf
                        <input type="hidden" name="entry_method" value="manual">
                        <button class="btn good" type="submit">Check In</button>
                    </form>
                @endif
                <div style="margin-top:12px"><a href="{{ route('admin.attendance.index', ['search' => $student->student_code]) }}">View attendance history</a></div>
            </div>

            <div class="card">
                <h2 style="margin-top:0">Current Seat</h2>
                @php($allocation = $student->seatAllocations->where('status','active')->sortByDesc('id')->first())
                @if($allocation)
                    <div class="label">Hall / Seat</div>
                    <div class="value">{{ $allocation->seat?->studyHall?->name }} / {{ $allocation->seat?->seat_no }}</div>
                    <div class="label" style="margin-top:12px">Timing</div>
                    <div class="value">{{ $allocation->start_time ?? 'Flexible' }} - {{ $allocation->end_time ?? 'Flexible' }}</div>
                @else
                    <div class="muted">No active seat allocation.</div>
                @endif
            </div>

            <div class="card">
                <h2 style="margin-top:0">Collect Fee</h2>
                @if($activeMembership)
                    @php
                        $paid = (float)$activeMembership->payments->whereIn('payment_status',['paid','partial'])->sum('amount');
                        $due = max(0, (float)$activeMembership->final_fee - $paid);
                    @endphp
                    <div class="field"><div class="label">Total Fee</div><div class="value">₹{{ number_format((float)$activeMembership->final_fee,2) }}</div></div>
                    <div class="field"><div class="label">Paid</div><div class="value">₹{{ number_format($paid,2) }}</div></div>
                    <div class="field"><div class="label">Due</div><div class="value">₹{{ number_format($due,2) }}</div></div>

                    @if($due > 0)
                        <form method="POST" action="{{ route('admin.students.payments.store', $student) }}">
                            @csrf
                            <input type="hidden" name="student_membership_id" value="{{ $activeMembership->id }}">
                            <div class="field"><label>Amount<input type="number" step="0.01" min="1" max="{{ $due }}" name="amount" required value="{{ old('amount') }}"></label></div>
                            <div class="field"><label>Payment Mode<select name="payment_mode" required><option value="cash">Cash</option><option value="upi">UPI</option><option value="card">Card</option><option value="bank_transfer">Bank Transfer</option><option value="other">Other</option></select></label></div>
                            <div class="field"><label>Transaction Ref<input type="text" name="transaction_ref" value="{{ old('transaction_ref') }}"></label></div>
                            <div class="field"><label>Remarks<textarea name="remarks" rows="3">{{ old('remarks') }}</textarea></label></div>
                            <button class="btn" type="submit">Receive Payment</button>
                        </form>
                    @else
                        <div style="padding:10px;border-radius:9px;background:#f0fdf4">Membership fee fully paid.</div>
                    @endif
                @else
                    <div class="muted">No active membership available for payment.</div>
                @endif
            </div>
        </div>
    </div>
</div>
</body>
</html>
