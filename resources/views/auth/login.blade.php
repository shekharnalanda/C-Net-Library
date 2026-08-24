<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">\n    <link rel="icon" type="image/png" href="{{ asset('images/cnet-library-icon.png') }}">
    <title>Admin Login | C-Net Library</title>
    <style>
        body { margin:0; font-family:Arial,sans-serif; background:#f4f6f8; display:grid; place-items:center; min-height:100vh; }
        .card { width:min(420px,92vw); background:white; padding:32px; border-radius:14px; box-shadow:0 12px 35px rgba(0,0,0,.08); }
        h1 { margin:0 0 8px; font-size:28px; }
        p { margin:0 0 24px; color:#667085; }
        label { display:block; margin:14px 0 6px; font-weight:600; }
        input { width:100%; box-sizing:border-box; padding:12px 14px; border:1px solid #d0d5dd; border-radius:8px; }
        button { width:100%; margin-top:20px; padding:12px 16px; border:0; border-radius:8px; background:#111827; color:white; font-weight:700; cursor:pointer; }
        .error { background:#fee2e2; color:#991b1b; padding:10px 12px; border-radius:8px; margin-bottom:16px; }
        .remember { display:flex; gap:8px; align-items:center; margin-top:12px; }
        .remember input { width:auto; }
    </style>
</head>
<body>
    <div class="card">
        <a href="{{ route('home') }}" aria-label="C-Net Library Home"><img src="{{ asset('images/cnet-library-logo.png') }}" alt="C-Net Library" style="display:block;width:290px;max-width:100%;height:auto;margin:0 auto 18px"></a>
        <p>Admin & staff login</p>

        @if ($errors->any())
            <div class="error">{{ $errors->first() }}</div>
        @endif

        <form method="POST" action="{{ route('login.store') }}">
            @csrf

            <label for="email">Email</label>
            <input id="email" type="email" name="email" value="{{ old('email') }}" required autofocus>

            <label for="password">Password</label>
            <input id="password" type="password" name="password" required>

            <label class="remember">
                <input type="checkbox" name="remember" value="1">
                <span>Remember me</span>
            </label>

            <button type="submit">Login</button>
        </form>
    </div>
</body>
</html>
