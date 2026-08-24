<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1.0">
    <link rel="icon" type="image/png" href="{{ asset('images/cnet-library-icon.png') }}">
    <meta name="referrer" content="no-referrer">
    <meta name="robots" content="noindex,nofollow,noarchive">
    <title>Student ID Card - {{ $student->name }}</title>
    <style>
        *{box-sizing:border-box}
        :root{--navy:#102a43;--teal:#0f766e;--green:#62a744;--line:#d6e0e8;--muted:#60758a}
        body{margin:0;background:#edf2f6;color:#172033;font-family:Arial,sans-serif}
        .toolbar{display:flex;justify-content:center;gap:12px;padding:16px;position:sticky;top:0;z-index:5;background:#edf2f6}
        .btn{background:var(--navy);color:#fff;border:0;border-radius:9px;padding:10px 16px;text-decoration:none;cursor:pointer;font-weight:700}
        .sheet{width:190mm;min-height:277mm;margin:0 auto 18px;padding:10mm 6mm;background:#fff;display:flex;justify-content:center;align-items:flex-start;gap:6mm}
        .card{width:85.6mm;height:128mm;flex:0 0 85.6mm;overflow:hidden;border:1px solid #c8d5df;border-radius:5mm;background:#fff;position:relative;box-shadow:0 10px 28px rgba(15,42,67,.12)}
        .top{height:24mm;padding:3mm 4mm;background:linear-gradient(125deg,var(--navy),var(--teal));color:#fff;border-bottom:2.5mm solid var(--green)}
        .brand{display:flex;align-items:center;gap:3mm}
        .brand img{width:18mm;height:15mm;object-fit:contain;border-radius:2mm;background:#fff;padding:1mm}
        .school{font-size:15px;font-weight:900;line-height:1.05}.subtitle{font-size:7px;letter-spacing:1px;margin-top:1.5mm}
        .front-body{padding:4mm 5mm 2mm}.profile{display:flex;align-items:center;gap:4mm;margin-bottom:3mm}
        .photo{width:25mm;height:30mm;border:1mm solid var(--teal);border-radius:3mm;overflow:hidden;background:#f1f5f9;flex:0 0 auto}
        .photo img{width:100%;height:100%;object-fit:cover}.placeholder{width:100%;height:100%;display:flex;align-items:center;justify-content:center;text-align:center;font-size:7px;font-weight:800;color:var(--muted)}
        .name{font-size:14px;font-weight:900;color:var(--navy);line-height:1.15}.code{display:inline-block;margin-top:2mm;background:#e7f5f2;color:var(--teal);border-radius:12px;padding:1.2mm 2mm;font-size:7px;font-weight:900}
        .line{display:grid;grid-template-columns:27mm 1fr;gap:2mm;padding:1.25mm 0;border-bottom:1px dashed #d6e0e8;font-size:8px;line-height:1.25}.line b{color:var(--muted)}
        .lower{display:flex;justify-content:space-between;align-items:flex-start;padding:2mm 5mm 12mm}
        .qr{width:21mm;height:21mm;border:1px solid var(--teal);padding:1mm}.qr img{width:100%;height:100%}
        .valid{text-align:center;width:39mm;font-size:7px}.valid-date{font-size:11px;font-weight:900;color:var(--navy);margin:1mm}.sig{height:7mm;border-bottom:1px solid #718096;margin-bottom:1mm}
        .footer{position:absolute;bottom:0;left:0;right:0;background:var(--navy);border-top:1mm solid var(--green);color:#fff;text-align:center;padding:2mm;font-size:6px}
        .back-head{height:23mm;padding:4mm;background:linear-gradient(125deg,var(--navy),var(--teal));border-bottom:2mm solid var(--green);color:#fff;text-align:center}
        .back-head img{width:38mm;height:13mm;object-fit:contain;background:#fff;border-radius:2mm;padding:1mm}.back-title{font-size:8px;font-weight:800;margin-top:1.5mm;letter-spacing:.8px}
        .back-body{padding:4mm 6mm 12mm}.rule{padding:2.3mm 0;border-bottom:1px solid #dfe6ed;font-size:8px;line-height:1.4}
        .contact{margin-top:3mm;background:#f1f7f7;border-radius:3mm;padding:3mm;font-size:8px;line-height:1.5}.student-ref{text-align:center;margin-top:3mm;font-size:8px;font-weight:900;color:var(--navy)}
        .privacy{max-width:190mm;margin:0 auto 18px;text-align:center;color:var(--muted);font-size:12px}
        @media(max-width:760px){.sheet{width:auto;min-height:0;margin:0 12px;padding:18px;flex-direction:column;align-items:center}.card{max-width:100%}.toolbar{flex-direction:column;align-items:stretch}.btn{text-align:center}}
        @media print{
            body{background:#fff;-webkit-print-color-adjust:exact;print-color-adjust:exact}
            .toolbar,.privacy{display:none!important}
            .sheet{width:210mm;height:297mm;min-height:0;margin:0;padding:10mm 13mm;gap:6mm;overflow:hidden;box-shadow:none;display:flex!important;flex-direction:row!important;align-items:flex-start!important;justify-content:center!important}
            .card{width:85.6mm!important;height:128mm!important;flex:0 0 85.6mm!important;max-width:none!important;box-shadow:none}
            @page{size:A4 portrait;margin:0}
        }
    </style>
</head>
<body>
@php($branch = $student->branch)
@php($membership = $student->activeMembership)
<div class="toolbar">
    <a class="btn" href="{{ !empty($adminView) ? route('admin.students.show', $student) : route('student.dashboard') }}">← {{ !empty($adminView) ? 'Student Profile' : 'Student Dashboard' }}</a>
    <button class="btn" onclick="window.print()">Print Front &amp; Back on A4</button>
</div>
<div class="sheet">
    <section class="card" aria-label="Student ID card front">
        <div class="top"><div class="brand"><img src="{{ asset('images/cnet-library-logo.png') }}" alt="C-Net Library"><div><div class="school">C-Net Library</div><div class="subtitle">STUDENT IDENTITY CARD</div></div></div></div>
        <div class="front-body">
            <div class="profile">
                <div class="photo">@if($student->photo)<img src="{{ asset('storage/'.$student->photo) }}" alt="{{ $student->name }} photo">@else<div class="placeholder">STUDENT<br>PHOTO</div>@endif</div>
                <div><div class="name">{{ $student->name }}</div><div class="code">{{ $student->student_code }}</div></div>
            </div>
            <div class="line"><b>Branch</b><span>{{ $branch?->name ?? '—' }}</span></div>
            <div class="line"><b>Mobile</b><span>{{ $student->mobile ?: '—' }}</span></div>
            <div class="line"><b>Guardian</b><span>{{ $student->guardian_name ?: ($student->father_name ?: '—') }}</span></div>
            <div class="line"><b>Study Slot</b><span>{{ $membership?->studySlot?->name ?? '—' }}</span></div>
            <div class="line"><b>Membership</b><span>{{ $membership?->feePlan?->name ?? '—' }}</span></div>
        </div>
        <div class="lower">
            <div><div class="qr"><img src="{{ $qrDataUri }}" alt="Attendance QR code"></div><div style="font-size:6px;text-align:center;margin-top:1mm">Attendance QR</div></div>
            <div class="valid"><b>VALID UNTIL</b><div class="valid-date">{{ $membership?->expiry_date?->format('d M Y') ?? '—' }}</div><div class="sig"></div>Authorised Signatory</div>
        </div>
        <div class="footer">Study · Learn · Grow · C-Net Library</div>
    </section>

    <section class="card" aria-label="Student ID card back">
        <div class="back-head"><img src="{{ asset('images/cnet-library-logo.png') }}" alt="C-Net Library"><div class="back-title">STUDENT IDENTITY CARD — BACK</div></div>
        <div class="back-body">
            <div class="rule"><b>Important Instructions</b></div>
            <div class="rule">This card is issued only to the registered C-Net Library member and is non-transferable.</div>
            <div class="rule">The member should carry this card during library visits and present it when requested by authorised staff.</div>
            <div class="rule">Loss or misuse of this card must be reported to the library office immediately.</div>
            <div class="rule">The QR code is intended only for authorised attendance verification.</div>
            <div class="rule">If found, please return this card to the C-Net Library branch shown below.</div>
            <div class="contact"><b>{{ $branch?->name ?? 'C-Net Library' }}</b><br>{{ $branch?->address ?: 'Near Kalawati Palace, Quamruddin Ganj, Bihar Sharif, Nalanda - 803101' }}@if($branch?->phone)<br>Phone: {{ $branch->phone }}@endif @if($branch?->email)<br>Email: {{ $branch->email }}@endif</div>
            <div class="student-ref">Student ID: {{ $student->student_code }}</div>
        </div>
        <div class="footer">C-Net Library · Member Identification</div>
    </section>
</div>
<div class="privacy">A4 portrait layout: front and back are printed together. Use 100% / Actual Size in the browser print dialog.</div>
</body>
</html>
