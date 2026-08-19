<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Digital Library | C-Net Library</title>
    <style>
        body{font-family:Arial,sans-serif;margin:0;background:#f6f8fb;color:#172033}.wrap{max-width:1180px;margin:auto;padding:24px}.nav{display:flex;gap:14px;flex-wrap:wrap;margin-bottom:24px}.nav a{text-decoration:none;color:#172033}.hero{background:#111827;color:#fff;border-radius:18px;padding:34px;margin-bottom:24px}.filters{display:grid;grid-template-columns:1fr 220px auto;gap:10px;margin-bottom:20px}.filters input,.filters select,.filters button{padding:11px;border:1px solid #cbd5e1;border-radius:9px}.filters button{background:#111827;color:#fff}.grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(250px,1fr));gap:16px}.card{background:#fff;border:1px solid #e2e8f0;border-radius:14px;padding:18px}.badge{display:inline-block;padding:5px 8px;background:#eef2ff;border-radius:999px;font-size:12px}.card a{display:inline-block;margin-top:12px;text-decoration:none;font-weight:700}.muted{color:#64748b;font-size:14px}@media(max-width:720px){.filters{grid-template-columns:1fr}}
    </style>
</head>
<body>
<div class="wrap">
    <nav class="nav">
        <a href="{{ route('home') }}">Home</a>
        <a href="{{ route('jobs.index') }}">Jobs</a>
        <a href="{{ route('admission.create') }}">Admission</a>
        <a href="{{ route('enquiry.create') }}">Enquiry</a>
        <a href="{{ route('login') }}">Student Login</a>
    </nav>

    <section class="hero">
        <h1 style="margin-top:0">Digital Library</h1>
        <p>Public notes, question papers, ebooks, videos and useful learning links from C-Net Library.</p>
    </section>

    <form class="filters" method="get">
        <input type="search" name="q" value="{{ request('q') }}" placeholder="Search title, category or topic">
        <select name="type">
            <option value="">All resource types</option>
            @foreach(['pdf'=>'PDF','ebook'=>'Ebook','notes'=>'Notes','question_paper'=>'Question Paper','video'=>'Video','link'=>'External Link'] as $value => $label)
                <option value="{{ $value }}" @selected(request('type') === $value)>{{ $label }}</option>
            @endforeach
        </select>
        <button type="submit">Search</button>
    </form>

    <div class="grid">
        @forelse($resources as $resource)
            <article class="card">
                <span class="badge">{{ ucwords(str_replace('_',' ', $resource->resource_type)) }}</span>
                <h3>{{ $resource->title }}</h3>
                @if($resource->category)<div class="muted">{{ $resource->category }}</div>@endif
                <p>{{ \Illuminate\Support\Str::limit($resource->description, 160) }}</p>
                <a href="{{ route('digital-library.access', $resource) }}" target="_blank" rel="noopener">Open resource →</a>
                @if($resource->file_path && $resource->download_allowed)
                    <div><a href="{{ route('digital-library.access', ['resource' => $resource, 'download' => 1]) }}">Download →</a></div>
                @endif
            </article>
        @empty
            <p>No public resources found.</p>
        @endforelse
    </div>

    <div style="margin-top:24px">{{ $resources->links() }}</div>
</div>
</body>
</html>
