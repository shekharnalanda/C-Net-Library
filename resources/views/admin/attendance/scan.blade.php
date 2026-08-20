<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="referrer" content="no-referrer">
    <meta name="robots" content="noindex,nofollow,noarchive">
    <title>QR Attendance Scanner - C-Net Library</title>
    <style>
        body{font-family:Arial,sans-serif;background:#f5f7fb;margin:0;color:#1f2937}.wrap{max-width:760px;margin:35px auto;padding:0 18px}.card{background:#fff;border:1px solid #e5e7eb;border-radius:16px;padding:20px;box-shadow:0 8px 28px rgba(15,23,42,.06);margin-bottom:18px}.row{display:grid;grid-template-columns:1fr auto;gap:10px}input{width:100%;box-sizing:border-box;padding:12px;border:1px solid #d1d5db;border-radius:10px}.btn{display:inline-block;background:#111827;color:#fff;border:0;border-radius:10px;padding:11px 16px;text-decoration:none;cursor:pointer}.btn.alt{background:#2563eb}.btn.warn{background:#b45309}.muted{color:#6b7280}.student{display:grid;grid-template-columns:1fr 1fr;gap:12px}.label{font-size:12px;text-transform:uppercase;color:#6b7280}.value{font-weight:700;margin-top:3px}@media(max-width:650px){.row,.student{grid-template-columns:1fr}}
    </style>
</head>
<body>
<div class="wrap">
    <div style="display:flex;justify-content:space-between;gap:12px;align-items:center;margin-bottom:18px">
        <div><h1 style="margin:0">QR Attendance Scanner</h1><div class="muted">Scan a student ID QR, or paste a QR token manually.</div></div>
        <a class="btn" href="{{ route('admin.attendance.index') }}">Attendance</a>
    </div>

    @if(session('success'))
        <div class="card" style="background:#f0fdf4;border-color:#86efac">{{ session('success') }}</div>
    @endif

    @if($errors->any())
        <div class="card" style="background:#fef2f2;border-color:#fca5a5">
            <ul style="margin:0;padding-left:18px">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
        </div>
    @endif

    <div class="card">
        <form method="POST" action="{{ route('admin.attendance.scan.lookup') }}" autocomplete="off">
            @csrf
            <div class="row">
                <input type="password" name="token" value="" placeholder="QR token" autocomplete="off" autofocus required>
                <button class="btn alt" type="submit">Find Student</button>
            </div>
        </form>
    </div>

    @if($lookupAttempted && !$student)
        <div class="card" style="background:#fff7ed;border-color:#fdba74">No student found for this QR token.</div>
    @endif

    @if($student && $challenge)
        <div class="card">
            <h2 style="margin-top:0">{{ $student->name }}</h2>
            <div class="student">
                <div><div class="label">Student ID</div><div class="value">{{ $student->student_code }}</div></div>
                <div><div class="label">Branch</div><div class="value">{{ $student->branch?->name ?? '—' }}</div></div>
                <div><div class="label">Mobile</div><div class="value">{{ $student->mobile }}</div></div>
                <div><div class="label">Membership</div><div class="value">{{ $student->activeMembership?->studySlot?->name ?? 'No active membership' }}</div></div>
            </div>
        </div>

        <div class="card">
            <div style="display:flex;gap:10px;flex-wrap:wrap">
                <form method="POST" action="{{ route('admin.attendance.scan.mark', $student) }}">
                    @csrf
                    <input type="hidden" name="challenge" value="{{ $challenge }}">
                    <input type="hidden" name="action" value="check_in">
                    <button class="btn alt" type="submit">Check In</button>
                </form>
                <form method="POST" action="{{ route('admin.attendance.scan.mark', $student) }}">
                    @csrf
                    <input type="hidden" name="challenge" value="{{ $challenge }}">
                    <input type="hidden" name="action" value="check_out">
                    <button class="btn warn" type="submit">Check Out</button>
                </form>
            </div>
            <div class="muted" style="margin-top:10px">This confirmation challenge expires quickly and can be used once.</div>
        </div>
    @endif
</div>
</body>
</html>
