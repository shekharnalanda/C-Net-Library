<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="referrer" content="no-referrer">
    <meta name="robots" content="noindex,nofollow,noarchive">
    <title>Activate Student Portal | C-Net Library</title>
    <style>
        body{font-family:Arial,sans-serif;background:#f3f6fa;margin:0;color:#1f2937}.wrap{max-width:520px;margin:70px auto;padding:20px}.card{background:#fff;border:1px solid #e5e7eb;border-radius:16px;padding:26px;box-shadow:0 12px 32px rgba(15,23,42,.08)}h1{margin-top:0}.muted{color:#64748b}.field{margin:16px 0}label{display:block;font-weight:700;font-size:14px;margin-bottom:6px}input{width:100%;box-sizing:border-box;padding:12px;border:1px solid #cbd5e1;border-radius:9px}.btn{width:100%;padding:12px;border:0;border-radius:9px;background:#0f766e;color:#fff;font-weight:700;cursor:pointer}.errors{background:#fef2f2;border:1px solid #fca5a5;padding:12px;border-radius:9px;margin-bottom:14px}
    </style>
</head>
<body>
<div class="wrap">
    <div class="card">
        <h1>Activate Student Portal</h1>
        <p class="muted">Hello {{ $student->name }}. Set your password to activate your C-Net Library student account.</p>
        <p><strong>Student ID:</strong> {{ $student->student_code }}</p>
        <p><strong>Login email:</strong> {{ $student->user?->email }}</p>

        @if($errors->any())
            <div class="errors"><ul style="margin:0;padding-left:18px">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
        @endif

        <form method="POST" action="{{ route('student.activate.store', $token) }}">
            @csrf
            <div class="field">
                <label for="password">New Password</label>
                <input id="password" type="password" name="password" required minlength="10" autocomplete="new-password">
                <div class="muted" style="font-size:13px;margin-top:6px">Use at least 10 characters with uppercase, lowercase, a number, and a symbol.</div>
            </div>
            <div class="field">
                <label for="password_confirmation">Confirm Password</label>
                <input id="password_confirmation" type="password" name="password_confirmation" required minlength="10" autocomplete="new-password">
            </div>
            <button class="btn" type="submit">Activate Portal</button>
        </form>
    </div>
</div>
</body>
</html>
