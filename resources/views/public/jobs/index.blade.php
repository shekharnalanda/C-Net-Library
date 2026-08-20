<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Jobs & Career - C-Net Library</title>
    <style>
        body{font-family:Arial,sans-serif;background:#f5f7fb;margin:0;color:#1f2937}.wrap{max-width:1100px;margin:30px auto;padding:0 18px}.hero{background:#111827;color:#fff;border-radius:18px;padding:28px;margin-bottom:18px}.filters,.card{background:#fff;border:1px solid #e5e7eb;border-radius:14px;padding:16px;margin-bottom:14px}.grid{display:grid;grid-template-columns:repeat(4,1fr);gap:10px}.jobs{display:grid;grid-template-columns:1fr 1fr;gap:14px}.job{background:#fff;border:1px solid #e5e7eb;border-radius:14px;padding:18px}.muted{color:#6b7280}.tag{display:inline-block;background:#eef2ff;padding:5px 8px;border-radius:999px;font-size:12px;margin-right:6px}.btn{display:inline-block;background:#111827;color:#fff;text-decoration:none;padding:10px 13px;border-radius:9px;border:0;cursor:pointer}.btn.alt{background:#fff;color:#111827;border:1px solid #d1d5db}.actions{display:flex;gap:8px;flex-wrap:wrap;margin-top:12px}input,select{width:100%;box-sizing:border-box;padding:10px;border:1px solid #d1d5db;border-radius:9px}@media(max-width:800px){.grid,.jobs{grid-template-columns:1fr}}
    </style>
</head>
<body>
<div class="wrap">
    <div class="hero">
        <h1 style="margin-top:0">Job & Career Opportunities</h1>
        <p>Verified summaries with direct links to official recruitment or application pages.</p>
        @auth
            @if(auth()->user()->role === 'student')
                <a class="btn alt" href="{{ route('student.saved-jobs.index') }}">View Saved Jobs</a>
            @endif
        @endauth
    </div>

    @if(session('success'))<div class="card" style="border-color:#86efac;background:#f0fdf4">{{ session('success') }}</div>@endif

    <form class="filters" method="GET">
        <div class="grid">
            <input type="text" name="q" value="{{ request('q') }}" placeholder="Search job or organization">
            <select name="type">
                <option value="">All Types</option>
                @foreach(['government','private','internship','apprenticeship'] as $type)
                    <option value="{{ $type }}" @selected(request('type')===$type)>{{ ucfirst($type) }}</option>
                @endforeach
            </select>
            <input type="text" name="qualification" value="{{ request('qualification') }}" placeholder="Qualification">
            <input type="text" name="location" value="{{ request('location') }}" placeholder="Location">
        </div>
        <button class="btn" style="margin-top:10px" type="submit">Filter Jobs</button>
    </form>

    <div class="jobs">
        @forelse($jobs as $job)
            <div class="job">
                <div>
                    <span class="tag">{{ ucfirst($job->job_type) }}</span>
                    @if($job->is_featured)<span class="tag">Featured</span>@endif
                </div>
                <h2>{{ $job->title }}</h2>
                <div class="muted">{{ $job->organization }}</div>
                @if($job->qualification)<p><strong>Qualification:</strong> {{ $job->qualification }}</p>@endif
                @if($job->location)<p><strong>Location:</strong> {{ $job->location }}</p>@endif
                @if($job->last_date)<p><strong>Last Date:</strong> {{ $job->last_date->format('d M Y') }}</p>@endif
                @if($job->summary)<p>{{ $job->summary }}</p>@endif
                <div class="actions">
                    <a class="btn" href="{{ $job->official_url }}" target="_blank" rel="noopener noreferrer">Official Apply / Details</a>
                    @auth
                        @if(auth()->user()->role === 'student')
                            <form method="POST" action="{{ route('student.saved-jobs.store', $job) }}">
                                @csrf
                                <button class="btn alt" type="submit">Save Job</button>
                            </form>
                        @endif
                    @endauth
                </div>
            </div>
        @empty
            <div class="card">No active job listings found.</div>
        @endforelse
    </div>

    <div style="margin-top:18px">{{ $jobs->links() }}</div>
</div>
</body>
</html>
