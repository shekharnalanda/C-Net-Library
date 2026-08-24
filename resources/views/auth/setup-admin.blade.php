<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">\n    <link rel="icon" type="image/png" href="{{ asset('images/cnet-library-icon.png') }}">
    <title>Set up C-Net Library Admin</title>
    <style>
        body{font-family:Arial,sans-serif;background:#f5f7fb;margin:0;padding:32px;color:#1f2937}.card{max-width:520px;margin:5vh auto;background:#fff;padding:28px;border-radius:14px;box-shadow:0 10px 30px rgba(0,0,0,.08)}h1{margin-top:0}.field{margin:16px 0}label{display:block;font-weight:600;margin-bottom:6px}input{width:100%;box-sizing:border-box;padding:11px 12px;border:1px solid #d1d5db;border-radius:8px;font-size:16px}button{width:100%;padding:12px;border:0;border-radius:8px;background:#111827;color:white;font-size:16px;font-weight:700;cursor:pointer}.help{font-size:13px;color:#6b7280}.errors{background:#fef2f2;color:#991b1b;padding:12px;border-radius:8px;margin-bottom:16px}
    </style>
</head>
<body>
<div class="card">
    <a href="{{ route('home') }}" aria-label="C-Net Library Home"><img src="{{ asset('images/cnet-library-logo.png') }}" alt="C-Net Library" style="display:block;width:290px;max-width:100%;height:auto;margin:0 auto 18px"></a>\n    <h1>Create First Admin</h1>
    <p>This page works only until the first super administrator is created.</p>

    @if ($errors->any())
        <div class="errors">
            <ul>
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form method="POST" action="{{ route('setup-admin.store') }}">
        @csrf
        <div class="field">
            <label for="name">Name</label>
            <input id="name" name="name" value="{{ old('name') }}" required autocomplete="name">
        </div>
        <div class="field">
            <label for="email">Email</label>
            <input id="email" type="email" name="email" value="{{ old('email') }}" required autocomplete="email">
        </div>
        <div class="field">
            <label for="password">Password</label>
            <input id="password" type="password" name="password" required autocomplete="new-password">
            <div class="help">Minimum 12 characters with uppercase, lowercase, number and symbol.</div>
        </div>
        <div class="field">
            <label for="password_confirmation">Confirm Password</label>
            <input id="password_confirmation" type="password" name="password_confirmation" required autocomplete="new-password">
        </div>
        <button type="submit">Create Admin</button>
    </form>
</div>
</body>
</html>
