<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Digital ID - {{ $student->name }}</title>
    <style>
        body{font-family:Arial,sans-serif;background:#eef2f7;margin:0;color:#111827}.wrap{max-width:760px;margin:32px auto;padding:0 18px}.toolbar{display:flex;justify-content:space-between;gap:10px;margin-bottom:16px}.btn{background:#111827;color:#fff;border:0;border-radius:9px;padding:10px 14px;text-decoration:none;cursor:pointer}.id{background:#fff;border:1px solid #dbe3ec;border-radius:18px;overflow:hidden;box-shadow:0 14px 38px rgba(15,23,42,.12)}.head{padding:20px;background:#111827;color:#fff}.body{padding:22px;display:grid;grid-template-columns:1fr 220px;gap:22px}.name{font-size:28px;font-weight:700}.muted{color:#6b7280}.token{word-break:break-all;background:#f8fafc;border:1px dashed #94a3b8;border-radius:12px;padding:14px;font-family:monospace;font-size:12px}.qrbox{border:1px solid #cbd5e1;border-radius:14px;padding:16px;text-align:center;display:flex;flex-direction:column;justify-content:center;align-items:center;min-height:180px}.qrbox img{width:190px;height:190px;max-width:100%}.foot{padding:14px 22px;background:#f8fafc;border-top:1px solid #e5e7eb;font-size:13px;color:#475569}@media(max-width:650px){.body{grid-template-columns:1fr}.toolbar{align-items:flex-start;flex-direction:column}}@media print{body{background:#fff}.toolbar{display:none}.wrap{margin:0;max-width:none}.id{box-shadow:none}}
    </style>
</head>
<body>
<div class="wrap">
    <div class="toolbar">
        <a class="btn" href="{{ route('student.dashboard') }}">Back to Dashboard</a>
        <button class="btn" onclick="window.print()">Print ID</button>
    </div>

    <div class="id">
        <div class="head">
            <div style="font-size:22px;font-weight:700">C-Net Library</div>
            <div style="opacity:.8">Digital Student ID</div>
        </div>
        <div class="body">
            <div>
                <div class="name">{{ $student->name }}</div>
                <p><strong>Student ID:</strong> {{ $student->student_code }}</p>
                <p><strong>Branch:</strong> {{ $student->branch?->name ?? '—' }}</p>
                <p><strong>Mobile:</strong> {{ $student->mobile }}</p>
                <p><strong>Membership:</strong> {{ $student->activeMembership?->status ? ucfirst($student->activeMembership->status) : '—' }}</p>
                <p><strong>Slot:</strong> {{ $student->activeMembership?->studySlot?->name ?? '—' }}</p>
                <p><strong>Valid Until:</strong> {{ $student->activeMembership?->expiry_date?->format('d M Y') ?? '—' }}</p>
                <div class="muted" style="margin-top:18px">Verification token</div>
                <div class="token">{{ $student->qr_token }}</div>
            </div>
            <div class="qrbox">
                <img src="{{ $qrDataUri }}" alt="Student QR code">
                <strong style="margin-top:8px">Scan for Attendance</strong>
                <div class="muted" style="font-size:12px;margin-top:6px;word-break:break-all">{{ $scanUrl }}</div>
            </div>
        </div>
        <div class="foot">This ID is valid only with an active C-Net Library membership. Keep the verification token private.</div>
    </div>
</div>
</body>
</html>
