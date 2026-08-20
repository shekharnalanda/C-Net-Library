<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Dashboard - C-Net Library</title>
    <style>
        body{font-family:Arial,sans-serif;background:#f5f7fb;margin:0;color:#1f2937}.wrap{max-width:1100px;margin:28px auto;padding:0 18px}.top{display:flex;justify-content:space-between;gap:16px;align-items:center;margin-bottom:20px}.actions{display:flex;gap:10px;flex-wrap:wrap}.btn{display:inline-block;background:#111827;color:#fff;text-decoration:none;padding:10px 14px;border-radius:9px;border:0;cursor:pointer}.btn.alt{background:#fff;color:#111827;border:1px solid #d1d5db}.grid{display:grid;grid-template-columns:repeat(4,1fr);gap:14px}.card{background:#fff;border:1px solid #e5e7eb;border-radius:14px;padding:18px;box-shadow:0 6px 22px rgba(15,23,42,.05);margin-bottom:18px}.metric{font-size:26px;font-weight:700}.muted{color:#6b7280}.two{display:grid;grid-template-columns:1fr 1fr;gap:18px}.table{width:100%;border-collapse:collapse}.table th,.table td{padding:9px;border-bottom:1px solid #edf0f4;text-align:left;font-size:14px}.quick{display:flex;gap:10px;flex-wrap:wrap;margin-bottom:18px}@media(max-width:850px){.grid{grid-template-columns:1fr 1fr}.two{grid-template-columns:1fr}.top{align-items:flex-start;flex-direction:column}}@media(max-width:520px){.grid{grid-template-columns:1fr}}
    </style>
</head>
<body>
<div class="wrap">
    <div class="top">
        <div>
            <h1 style="margin:0">Welcome, {{ $student->name }}</h1>
            <div class="muted">{{ $student->student_code }} · {{ $student->branch?->name ?? 'C-Net Library' }}</div>
        </div>
        <div class="actions">
            <a class="btn" href="{{ route('student.id-card') }}">Digital ID</a>
            <form method="POST" action="{{ route('logout') }}">@csrf<button class="btn" type="submit">Logout</button></form>
        </div>
    </div>

    <div class="quick">
        <a class="btn alt" href="{{ route('digital-library.index') }}">Digital Library</a>
        <a class="btn alt" href="{{ route('student.saved-jobs.index') }}">Saved Jobs</a>
        <a class="btn alt" href="{{ route('jobs.index') }}">Jobs & Career</a>
    </div>

    <div class="grid">
        <div class="card"><div class="muted">Membership</div><div class="metric">{{ $membership ? ucfirst($membership->status) : 'None' }}</div><div class="muted">{{ $membership?->expiry_date?->format('d M Y') ?? '—' }}</div></div>
        <div class="card"><div class="muted">Current Seat</div><div class="metric">{{ $activeSeat?->seat?->seat_no ?? '—' }}</div><div class="muted">{{ $activeSeat?->seat?->studyHall?->name ?? 'Not allocated' }}</div></div>
        <div class="card"><div class="muted">Fee Due</div><div class="metric">₹{{ number_format($due,2) }}</div><div class="muted">Paid ₹{{ number_format($paid,2) }} of ₹{{ number_format((float)($membership?->final_fee ?? 0),2) }}</div></div>
        <div class="card"><div class="muted">Recent Study Time</div><div class="metric">{{ number_format($studyMinutes / 60,1) }}h</div><div class="muted">Last 10 sessions</div></div>
    </div>

    <div class="two">
        <div class="card">
            <h2 style="margin-top:0">Membership & Seat</h2>
            <p><strong>Plan:</strong> {{ $membership?->feePlan?->name ?? '—' }}</p>
            <p><strong>Slot:</strong> {{ $membership?->studySlot?->name ?? '—' }}</p>
            <p><strong>Validity:</strong> {{ $membership?->start_date?->format('d M Y') ?? '—' }} to {{ $membership?->expiry_date?->format('d M Y') ?? '—' }}</p>
            <p><strong>Total Fee:</strong> ₹{{ number_format((float)($membership?->final_fee ?? 0),2) }}</p>
            <p><strong>Paid:</strong> ₹{{ number_format($paid,2) }} · <strong>Due:</strong> ₹{{ number_format($due,2) }}</p>
            <p><strong>Seat:</strong> {{ $activeSeat?->seat?->studyHall?->name ?? '—' }} / {{ $activeSeat?->seat?->seat_no ?? '—' }}</p>
        </div>

        <div class="card">
            <h2 style="margin-top:0">Recent Attendance</h2>
            <table class="table">
                <thead><tr><th>Date</th><th>In</th><th>Out</th><th>Minutes</th></tr></thead>
                <tbody>
                @forelse($student->attendances as $attendance)
                    <tr><td>{{ $attendance->attendance_date?->format('d M') }}</td><td>{{ $attendance->check_in_at?->format('h:i A') ?? '—' }}</td><td>{{ $attendance->check_out_at?->format('h:i A') ?? 'Inside' }}</td><td>{{ $attendance->study_minutes }}</td></tr>
                @empty
                    <tr><td colspan="4" class="muted">No attendance yet.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="card">
        <h2 style="margin-top:0">Recent Books</h2>
        <div style="overflow:auto">
            <table class="table">
                <thead><tr><th>Book</th><th>Issued</th><th>Due</th><th>Status</th></tr></thead>
                <tbody>
                @forelse($student->bookIssues as $issue)
                    <tr><td>{{ $issue->bookCopy?->book?->title ?? '—' }}</td><td>{{ $issue->issued_at?->format('d M Y') }}</td><td>{{ $issue->due_at?->format('d M Y') }}</td><td>{{ ucfirst($issue->status) }}</td></tr>
                @empty
                    <tr><td colspan="4" class="muted">No books issued.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
</body>
</html>
