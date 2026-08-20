<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Saved Jobs - C-Net Library</title>
    <style>
        body{font-family:Arial,sans-serif;background:#f5f7fb;margin:0;color:#1f2937}.wrap{max-width:1000px;margin:30px auto;padding:0 18px}.top{display:flex;justify-content:space-between;align-items:center;gap:12px;margin-bottom:18px}.card{background:#fff;border:1px solid #e5e7eb;border-radius:14px;padding:18px;margin-bottom:14px}.btn{display:inline-block;background:#111827;color:#fff;text-decoration:none;padding:10px 13px;border-radius:9px;border:0;cursor:pointer}.btn.alt{background:#fff;color:#111827;border:1px solid #d1d5db}.muted{color:#6b7280}.actions{display:flex;gap:8px;flex-wrap:wrap;margin-top:12px}@media(max-width:700px){.top{align-items:flex-start;flex-direction:column}}
    </style>
</head>
<body>
<div class="wrap">
    <div class="top">
        <div><h1 style="margin:0">Saved Jobs</h1><div class="muted">Your active saved career opportunities.</div></div>
        <div><a class="btn alt" href="{{ route('jobs.index') }}">Browse Jobs</a> <a class="btn" href="{{ route('student.dashboard') }}">Dashboard</a></div>
    </div>

    @if(session('success'))<div class="card" style="border-color:#86efac;background:#f0fdf4">{{ session('success') }}</div>@endif

    @forelse($jobs as $job)
        <div class="card">
            <h2 style="margin-top:0">{{ $job->title }}</h2>
            <div class="muted">{{ $job->organization }} · {{ ucfirst($job->job_type) }}</div>
            @if($job->location)<p><strong>Location:</strong> {{ $job->location }}</p>@endif
            @if($job->last_date)<p><strong>Last Date:</strong> {{ $job->last_date->format('d M Y') }}</p>@endif
            @if($job->summary)<p>{{ $job->summary }}</p>@endif
            <div class="actions">
                <a class="btn" href="{{ $job->official_url }}" target="_blank" rel="noopener noreferrer">Official Apply / Details</a>
                <form method="POST" action="{{ route('student.saved-jobs.destroy', $job) }}">
                    @csrf
                    @method('DELETE')
                    <button class="btn alt" type="submit">Remove</button>
                </form>
            </div>
        </div>
    @empty
        <div class="card">You have no active saved jobs.</div>
    @endforelse

    <div style="margin-top:18px">{{ $jobs->links() }}</div>
</div>
</body>
</html>
