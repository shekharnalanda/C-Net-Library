<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Students - C-Net Library</title>
    <style>
        *{box-sizing:border-box}body{font-family:Arial,sans-serif;background:#f5f7fb;margin:0;color:#1f2937}.wrap{max-width:1240px;margin:30px auto;padding:0 18px}.top{display:flex;justify-content:space-between;align-items:flex-start;gap:16px;margin-bottom:22px}.top h1{margin:0}.muted{color:#6b7280}.nav{display:flex;gap:8px;flex-wrap:wrap}.btn{display:inline-block;padding:10px 13px;border-radius:9px;border:0;text-decoration:none;background:#111827;color:#fff;font-weight:700}.btn.alt{background:#2563eb}.btn.light{background:#e5e7eb;color:#111827}.summary{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:14px;margin-bottom:18px}.stat,.card{background:#fff;border:1px solid #e5e7eb;border-radius:14px;box-shadow:0 6px 22px rgba(15,23,42,.05)}.stat{padding:16px}.stat .k{font-size:12px;text-transform:uppercase;color:#6b7280}.stat .v{font-size:28px;font-weight:800;margin-top:6px}.card{padding:18px}.filters{display:grid;grid-template-columns:minmax(240px,1fr) 180px auto auto;gap:10px;margin-bottom:16px}input,select,button{padding:10px 12px;border:1px solid #d1d5db;border-radius:9px;font-size:14px;background:#fff}button{background:#111827;color:#fff;font-weight:700;cursor:pointer}.table-wrap{overflow:auto}.table{width:100%;border-collapse:collapse;min-width:900px}.table th,.table td{padding:12px;border-bottom:1px solid #edf0f4;text-align:left;font-size:14px;vertical-align:top}.table th{font-size:12px;text-transform:uppercase;color:#6b7280}.status{display:inline-block;font-size:12px;padding:5px 9px;border-radius:999px;background:#eef2ff}.status.active{background:#dcfce7;color:#166534}.status.inactive{background:#f3f4f6;color:#4b5563}.status.blocked{background:#fee2e2;color:#991b1b}.link{color:#2563eb;text-decoration:none;font-weight:700}.sub{font-size:12px;color:#6b7280;margin-top:4px}.pagination{margin-top:18px}@media(max-width:800px){.top{flex-direction:column}.summary{grid-template-columns:repeat(2,1fr)}.filters{grid-template-columns:1fr}.wrap{margin-top:18px}}@media(max-width:480px){.summary{grid-template-columns:1fr}}
    </style>
</head>
<body>
<div class="wrap">
    <div class="top">
        <div>
            <h1>Student Registry</h1>
            <div class="muted">Search students, check membership/seat status, and open a full student account.</div>
        </div>
        <div class="nav">
            <a class="btn light" href="{{ route('admin.dashboard') }}">Dashboard</a>
            <a class="btn alt" href="{{ route('admin.admissions.index') }}">Admissions</a>
            <a class="btn" href="{{ route('admin.seats.available') }}">Available Seats</a>
        </div>
    </div>

    <div class="summary">
        <div class="stat"><div class="k">Total Students</div><div class="v">{{ $summary['total'] }}</div></div>
        <div class="stat"><div class="k">Active</div><div class="v">{{ $summary['active'] }}</div></div>
        <div class="stat"><div class="k">Inactive</div><div class="v">{{ $summary['inactive'] }}</div></div>
        <div class="stat"><div class="k">Blocked</div><div class="v">{{ $summary['blocked'] }}</div></div>
    </div>

    <div class="card">
        <form method="GET" class="filters">
            <input type="text" name="search" value="{{ request('search') }}" placeholder="Name, mobile, email, student ID">
            <select name="status">
                <option value="">All status</option>
                <option value="active" @selected(request('status') === 'active')>Active</option>
                <option value="inactive" @selected(request('status') === 'inactive')>Inactive</option>
                <option value="blocked" @selected(request('status') === 'blocked')>Blocked</option>
            </select>
            <button type="submit">Filter</button>
            <a class="btn light" href="{{ route('admin.students.index') }}">Reset</a>
        </form>

        <div class="table-wrap">
            <table class="table">
                <thead>
                <tr>
                    <th>Student</th><th>Contact</th><th>Branch</th><th>Membership</th><th>Seat</th><th>Status</th><th>Action</th>
                </tr>
                </thead>
                <tbody>
                @forelse($students as $student)
                    @php($membership = $student->activeMembership)
                    @php($allocation = $student->seatAllocations->where('status','active')->sortByDesc('id')->first())
                    <tr>
                        <td><strong>{{ $student->name }}</strong><div class="sub">{{ $student->student_code }}</div></td>
                        <td>{{ $student->mobile }}<div class="sub">{{ $student->email ?: 'No email' }}</div></td>
                        <td>{{ $student->branch?->name ?? '—' }}</td>
                        <td>
                            {{ $membership?->feePlan?->name ?? 'No active plan' }}
                            @if($membership?->studySlot)<div class="sub">{{ $membership->studySlot->name }}</div>@endif
                            @if($membership?->expiry_date)<div class="sub">Expires {{ $membership->expiry_date->format('d M Y') }}</div>@endif
                        </td>
                        <td>
                            @if($allocation)
                                {{ $allocation->seat?->seat_no ?? '—' }}
                                <div class="sub">{{ $allocation->seat?->studyHall?->name ?? '—' }}</div>
                            @else
                                <span class="muted">Not allocated</span>
                            @endif
                        </td>
                        <td><span class="status {{ $student->status }}">{{ ucfirst($student->status) }}</span></td>
                        <td><a class="link" href="{{ route('admin.students.show', $student) }}">Open Account →</a></td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="muted" style="padding:28px;text-align:center">No students found for the selected filters.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>

        <div class="pagination">{{ $students->links() }}</div>
    </div>
</div>
</body>
</html>
