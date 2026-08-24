<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">\n    <link rel="icon" type="image/png" href="{{ asset('images/cnet-library-icon.png') }}">
    <title>{{ $page->meta_title ?: $page->title.' | C-Net Library' }}</title>
    <meta name="description" content="{{ $page->meta_description ?: $page->excerpt }}">
    @if($page->meta_keywords)<meta name="keywords" content="{{ $page->meta_keywords }}">@endif
    @if($page->canonical_url)<link rel="canonical" href="{{ $page->canonical_url }}">@endif
    <style>
        *{box-sizing:border-box}body{margin:0;font-family:Inter,Arial,sans-serif;color:#152238;background:#f7f9fc;line-height:1.7}.container{max-width:980px;margin:auto;padding:0 20px}.nav{background:#fff;border-bottom:1px solid #e5e7eb}.navin{height:72px;display:flex;align-items:center;justify-content:space-between}.brand{font-weight:800;font-size:22px;color:#102a43;text-decoration:none}.btn{display:inline-block;padding:10px 15px;border-radius:9px;background:#0f766e;color:#fff;text-decoration:none;font-weight:700}.hero{background:linear-gradient(135deg,#e9fbf7,#eff6ff);padding:60px 0}.hero h1{font-size:42px;line-height:1.15;margin:0 0 12px;color:#102a43}.hero p{color:#486581;font-size:18px}.content{background:#fff;margin:34px auto;padding:32px;border:1px solid #e3e9ef;border-radius:16px}.content img{max-width:100%;height:auto}.footer{margin-top:55px;background:#102a43;color:#d9e2ec;padding:30px 0}
    </style>
</head>
<body>
<nav class="nav"><div class="container navin"><a class="brand" href="{{ route('home') }}" aria-label="C-Net Library Home"><img src="{{ asset('images/cnet-library-logo.png') }}" alt="C-Net Library" style="display:block;width:210px;max-width:48vw;height:auto"></a><a class="btn" href="{{ route('admission.create') }}">Join Now</a></div></nav>
<section class="hero"><div class="container"><h1>{{ $page->title }}</h1>@if($page->excerpt)<p>{{ $page->excerpt }}</p>@endif</div></section>
<main class="container"><article class="content">{!! $page->content !!}</article></main>
<footer class="footer"><div class="container">C-Net Library · Focused study, flexible plans and learning support.</div></footer>
</body>
</html>
