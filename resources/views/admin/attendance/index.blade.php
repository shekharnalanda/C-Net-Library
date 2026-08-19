<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Attendance - C-Net Library</title>
    <style>
        body{font-family:Arial,sans-serif;background:#f5f7fb;margin:0;color:#1f2937}.wrap{max-width:1180px;margin:30px auto;padding:0 18px}.top{display:flex;justify-content:space-between;gap:16px;align-items:center;margin-bottom:20px}.cards{display:grid;grid-template-columns:repeat(2,1fr);gap:14px;margin-bottom:18px}.card{background:#fff;border:1px solid #e5e7eb;border-radius:14px;padding:18px;box-shadow:0 6px 22px rgba(15,23,42,.05)}.metric{font-size:28px;font-weight:700}.muted{color:#6b7280}.filters{display:grid;grid-template-columns:2fr 1fr auto;gap:10px;margin-bottom:18px}.table{width:100%;border-collapse:collapse;background:#fff;border-radius:14px;overflow:hidden}.table th,.table td{padding:11px;border-bottom:1px solid #edf0f4;text-align:left;font-size:14px}.btn{display:inline-block;background:#111827;color:#fff;text-decoration:none;padding:9px 12px;border-radius:8px;border:0;cursor:pointer}.btn.alt{background:#2563eb}.btn.out{background:#b91c1c}input{width:100%;box-sizing:border-box;padding:10px;border:1px solid #d1d5db;border-radius:9px}@media(max-width:800px){.cards,.filters{grid-template-columns:1fr}.top{align-items:flex-start;flex-direction:column}.table{font-size:12px}}
    </style>
</head>
<body>
<div class="wrap">
    <div class="top">
        <div>
            <h1 style="margin:0">Student Attendance</h1>
            <div class="muted">Track entry, exit and actual study time.</div>
        </div>
        <a class="btn" href="{{ route('admin.dashboard') }}">Dashboard</a>
    </div>

    @if(session('success'))
        <div class="card" style="border-color:#86efac;background:#f0fdf4;margin-bottom:16px">{{ session('success') }}</div>
    @endif

    @if($errors->any())
        <div class="card" style="border-color:#fca5a5;background:#fef2f2;margin-bottom:16px">
            <ul style="margin:0;padding-left:18px">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
        </div>
    @endif

    <div class="cards">
        <div class="card"><div class="muted">Present Today</div><div class="metric">{{ $presentToday }}</div></div>
        <div class="card"><div class="muted">Currently Inside</div><div class="metric">{{ $currentlyInside }}</div></div>
    </div>

    <form method="GET" class="filters">
        <input type="text" name="search" value="{{ request('search') }}" placeholder="Search name, student code or mobile">
        <input type="date" name="date" value="{{ request('date') }}">
        <button class="btn" type="submit">Filter</button>
    </form>

    <div style="overflow:auto">
        <table class="table">
            <thead>
            <tr>
                <th>Student</th>
                <th>Date</th>
                <th>Check In</th>
                <th>Check Out</th>
                <th>Study Time</th>
                <th>Method</th>
            </tr>
            </thead>
            <tbody>
            @forelse($attendances as $attendance)
                <tr>
                    <td>
                        <a href="{{ route('admin.students.show', $attendance->student) }}">{{ $attendance->student?->name }}</a><br>
                        <span class="muted">{{ $attendance->student?->student_code }}</span>
                    </td>
                    <td>{{ $attendance->attendance_date?->format('d M Y') }}</td>
                    <td>{{ $attendance->check_in_at?->format('h:i A') ?? '—' }}</td>
                    <td>{{ $attendance->check_out_at?->format('h:i A') ?? 'Inside' }}</td>
                    <td>
                        @php($minutes = (int)$attendance->study_minutes)
                        {{ intdiv($minutes, 60) }}h {{ $minutes % 60 }}m
                    </td>
                    <td>{{ strtoupper(str_replace('_',' ', $attendance->entry_method)) }}</td>
                </tr>
            @empty
                <tr><td colspan="6" class="muted">No attendance records found.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>

    <div style="margin-top:16px">{{ $attendances->links() }}</div>
</div>
</body>
</html>
