<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Physical Library - C-Net Library</title>
    <style>
        body{font-family:Arial,sans-serif;background:#f5f7fb;margin:0;color:#1f2937}.wrap{max-width:1240px;margin:30px auto;padding:0 18px}.top{display:flex;justify-content:space-between;gap:12px;align-items:center}.nav{display:flex;gap:8px;flex-wrap:wrap}.card{background:#fff;border:1px solid #e5e7eb;border-radius:14px;padding:18px;margin-top:18px;box-shadow:0 6px 22px rgba(15,23,42,.05)}.table{width:100%;border-collapse:collapse}.table th,.table td{padding:10px;border-bottom:1px solid #edf0f4;text-align:left;font-size:14px;vertical-align:top}.btn{display:inline-block;background:#111827;color:#fff;border:0;border-radius:8px;padding:9px 12px;text-decoration:none;cursor:pointer}.btn.green{background:#047857}.btn.blue{background:#2563eb}.btn.warn{background:#b45309}.btn.red{background:#b91c1c}.btn.light{background:#fff;color:#111827;border:1px solid #d1d5db}input,select,textarea{padding:9px;border:1px solid #d1d5db;border-radius:8px}.muted{color:#6b7280}.grid{display:grid;grid-template-columns:1fr 1fr;gap:18px}.actions{display:flex;gap:8px;flex-wrap:wrap}.inline-form{display:flex;gap:6px;align-items:center;flex-wrap:wrap}.inline-form input,.inline-form select{max-width:150px}.pill{display:inline-block;padding:4px 8px;border-radius:999px;background:#eef2ff;font-size:12px}.pill.overdue{background:#fef2f2;color:#991b1b}.searchbar{display:grid;grid-template-columns:1fr auto auto;gap:8px;align-items:center}.section-head{display:flex;justify-content:space-between;gap:12px;align-items:center;flex-wrap:wrap}.status-available{color:#047857;font-weight:700}.status-issued{color:#1d4ed8;font-weight:700}.status-reserved{color:#b45309;font-weight:700}.status-lost{color:#b91c1c;font-weight:700}@media(max-width:800px){.grid,.searchbar{grid-template-columns:1fr}.top{align-items:flex-start;flex-direction:column}.inline-form input,.inline-form select{max-width:none;width:100%}}
    </style>
</head>
<body>
<div class="wrap">
    <div class="top">
        <div><h1 style="margin:0">Physical Library</h1><div class="muted">Books, reservations, issue/return, losses and charge collection</div></div>
        <div class="nav"><a class="btn light" href="{{ route('admin.students.index') }}">Students</a><a class="btn light" href="{{ route('admin.reports.index') }}">Reports</a><a class="btn" href="{{ route('admin.dashboard') }}">Dashboard</a></div>
    </div>

    @if(session('success'))<div class="card" style="background:#f0fdf4;border-color:#86efac">{{ session('success') }}</div>@endif
    @if($errors->any())<div class="card" style="background:#fef2f2;border-color:#fca5a5"><ul style="margin:0;padding-left:18px">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif

    <div class="card">
        <form method="GET" class="searchbar">
            <input type="text" name="search" value="{{ request('search') }}" placeholder="Search title, author, accession no. or barcode">
            <button class="btn" type="submit">Search Catalog</button>
            @if(request('search'))<a class="btn light" href="{{ route('admin.library.index') }}">Clear</a>@endif
        </form>
    </div>

    <div class="grid">
        <div class="card">
            <h2 style="margin-top:0">Issue Book</h2>
            <form method="POST" action="{{ route('admin.library.issue') }}">
                @csrf
                <div style="margin-bottom:12px"><label>Student</label><br><select name="student_id" required style="width:100%"><option value="">Select student</option>@foreach($students as $student)<option value="{{ $student->id }}">{{ $student->student_code }} - {{ $student->name }}</option>@endforeach</select></div>
                <div style="margin-bottom:12px"><label>Available / Reserved Copy</label><br><select name="book_copy_id" required style="width:100%"><option value="">Select book copy</option>@foreach($copies->filter(fn($copy) => in_array($copy->status,['available','reserved'],true)) as $copy)<option value="{{ $copy->id }}">{{ $copy->book?->title }} — {{ $copy->accession_no }}{{ $copy->status === 'reserved' ? ' [Reserved]' : '' }}</option>@endforeach</select></div>
                <div style="margin-bottom:12px"><label>Issue Days</label><br><input type="number" name="issue_days" value="14" min="1" max="90" style="width:100%;box-sizing:border-box"></div>
                <button class="btn blue" type="submit">Issue Book</button>
            </form>
        </div>

        <div class="card">
            <h2 style="margin-top:0">Reserve Copy</h2>
            <form method="POST" action="{{ route('admin.library.reservations.store') }}">
                @csrf
                <div style="margin-bottom:12px"><label>Student</label><br><select name="student_id" required style="width:100%"><option value="">Select student</option>@foreach($students as $student)<option value="{{ $student->id }}">{{ $student->student_code }} - {{ $student->name }}</option>@endforeach</select></div>
                <div style="margin-bottom:12px"><label>Available Copy</label><br><select name="book_copy_id" required style="width:100%"><option value="">Select book copy</option>@foreach($copies->where('status','available') as $copy)<option value="{{ $copy->id }}">{{ $copy->book?->title }} — {{ $copy->accession_no }}</option>@endforeach</select></div>
                <div style="margin-bottom:12px"><label>Expires At</label><br><input type="datetime-local" name="expires_at" required style="width:100%;box-sizing:border-box"></div>
                <div style="margin-bottom:12px"><label>Remarks</label><br><input type="text" name="remarks" maxlength="1000" style="width:100%;box-sizing:border-box"></div>
                <button class="btn" type="submit">Reserve Copy</button>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="section-head"><div><h2 style="margin:0">Circulation & Charges</h2><div class="muted">Open issues appear first; returned and lost records remain visible for audit.</div></div></div>
        <div style="overflow:auto;margin-top:12px">
            <table class="table">
                <thead><tr><th>Book</th><th>Student</th><th>Dates / Status</th><th>Outstanding</th><th>Actions</th></tr></thead>
                <tbody>
                @forelse($issues as $issue)
                    @php
                        $fineCollected = (float)$issue->chargePayments->where('charge_type','fine')->sum('amount');
                        $lossCollected = (float)$issue->chargePayments->where('charge_type','loss')->sum('amount');
                        $fineDue = max(0,(float)$issue->fine_amount-$fineCollected);
                        $lossDue = max(0,(float)$issue->loss_charge-$lossCollected);
                        $open = in_array($issue->status,['issued','overdue'],true);
                        $isLate = $open && $issue->due_at && $issue->due_at->isPast();
                    @endphp
                    <tr>
                        <td>{{ $issue->bookCopy?->book?->title }}<br><span class="muted">{{ $issue->bookCopy?->accession_no }}</span></td>
                        <td><a href="{{ route('admin.students.show',$issue->student) }}">{{ $issue->student?->name }}</a><br><span class="muted">{{ $issue->student?->student_code }}</span></td>
                        <td>Issued {{ $issue->issued_at?->format('d M Y') }}<br>Due {{ $issue->due_at?->format('d M Y') }}<br><span class="pill {{ $isLate ? 'overdue' : '' }}">{{ $isLate ? 'OVERDUE' : strtoupper($issue->status) }}</span></td>
                        <td>
                            @if($fineDue>0)<div>Fine ₹{{ number_format($fineDue,2) }}</div>@endif
                            @if($lossDue>0)<div>Loss ₹{{ number_format($lossDue,2) }}</div>@endif
                            @if($fineDue<=0 && $lossDue<=0)<span class="muted">Settled</span>@endif
                        </td>
                        <td>
                            @if($open)
                                <div class="actions">
                                    <form class="inline-form" method="POST" action="{{ route('admin.library.return', $issue) }}">@csrf<select name="return_condition"><option value="good">Good</option><option value="fair">Fair</option><option value="damaged">Damaged</option></select><button class="btn green" type="submit">Return</button></form>
                                    <form class="inline-form" method="POST" action="{{ route('admin.library.lost', $issue) }}">@csrf<input type="number" name="loss_charge" min="0" step="0.01" placeholder="Loss charge"><input type="text" name="remarks" maxlength="1000" placeholder="Loss reason" required><button class="btn warn" type="submit">Mark Lost</button></form>
                                </div>
                            @endif
                            @if($fineDue>0 || $lossDue>0)
                                <form class="inline-form" style="margin-top:8px" method="POST" action="{{ route('admin.library.charges.store', $issue) }}">
                                    @csrf
                                    <select name="charge_type">@if($fineDue>0)<option value="fine">Fine</option>@endif @if($lossDue>0)<option value="loss">Loss</option>@endif</select>
                                    <input type="number" name="amount" min="0.01" step="0.01" placeholder="Amount" required>
                                    <select name="payment_mode"><option value="cash">Cash</option><option value="upi">UPI</option><option value="card">Card</option><option value="bank_transfer">Bank</option><option value="other">Other</option></select>
                                    <input type="text" name="transaction_ref" placeholder="Txn ref">
                                    <button class="btn" type="submit">Collect</button>
                                </form>
                            @endif
                            @if($issue->status === 'lost' && $lossDue <= 0)
                                <form class="inline-form" style="margin-top:8px" method="POST" action="{{ route('admin.library.recover', $issue) }}">@csrf<select name="condition"><option value="good">Good</option><option value="fair">Fair</option><option value="damaged">Damaged</option></select><input type="text" name="remarks" maxlength="1000" placeholder="Recovery note" required><button class="btn green" type="submit">Recover Copy</button></form>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="muted">No circulation records.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="card">
        <div class="section-head"><div><h2 style="margin:0">Book Copies & Reservations</h2><div class="muted">Current catalog page and active reservation details.</div></div></div>
        <div style="overflow:auto;margin-top:12px">
            <table class="table">
                <thead><tr><th>Accession</th><th>Book</th><th>Branch</th><th>Condition</th><th>Status</th><th>Reservation</th></tr></thead>
                <tbody>
                @forelse($copies as $copy)
                    @php($reservation=$copy->reservations->first())
                    <tr>
                        <td>{{ $copy->accession_no }}@if($copy->barcode)<br><span class="muted">{{ $copy->barcode }}</span>@endif</td>
                        <td>{{ $copy->book?->title }}<br><span class="muted">{{ $copy->book?->author ?: '—' }}</span></td>
                        <td>{{ $copy->branch?->name }}</td><td>{{ ucfirst($copy->condition) }}</td>
                        <td><span class="status-{{ $copy->status }}">{{ ucfirst($copy->status) }}</span></td>
                        <td>
                            @if($reservation)
                                {{ $reservation->student?->student_code }} — {{ $reservation->student?->name }}<br><span class="muted">Until {{ $reservation->expires_at?->format('d M Y h:i A') }}</span>
                                <form method="POST" action="{{ route('admin.library.reservations.cancel',$reservation) }}" style="margin-top:6px">@csrf<button class="btn red" type="submit">Cancel Reservation</button></form>
                            @else
                                <span class="muted">—</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="muted">No book copies matched this search.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        <div style="margin-top:14px">{{ $copies->links() }}</div>
    </div>
</div>
</body>
</html>
