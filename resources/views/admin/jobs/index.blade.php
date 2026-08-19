<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Jobs - C-Net Library Admin</title>
    <style>
        body{font-family:Arial,sans-serif;background:#f5f7fb;margin:0;color:#1f2937}.wrap{max-width:1150px;margin:30px auto;padding:0 18px}.top{display:flex;justify-content:space-between;align-items:center;gap:14px;margin-bottom:18px}.grid{display:grid;grid-template-columns:1fr 1.4fr;gap:18px}.card{background:#fff;border:1px solid #e5e7eb;border-radius:14px;padding:18px;box-shadow:0 6px 22px rgba(15,23,42,.05)}.field{margin-bottom:11px}.row{display:grid;grid-template-columns:1fr 1fr;gap:10px}input,select,textarea{width:100%;box-sizing:border-box;padding:10px;border:1px solid #d1d5db;border-radius:9px;margin-top:5px}.btn{display:inline-block;background:#111827;color:#fff;text-decoration:none;padding:10px 13px;border-radius:9px;border:0;cursor:pointer}.muted{color:#6b7280}.table{width:100%;border-collapse:collapse}.table th,.table td{padding:10px;border-bottom:1px solid #edf0f4;text-align:left;font-size:14px;vertical-align:top}.notice{padding:10px;border-radius:9px;margin-bottom:12px}.ok{background:#f0fdf4;border:1px solid #86efac}.err{background:#fef2f2;border:1px solid #fca5a5}@media(max-width:850px){.grid,.row{grid-template-columns:1fr}.top{align-items:flex-start;flex-direction:column}}
    </style>
</head>
<body>
<div class="wrap">
    <div class="top">
        <div><h1 style="margin:0">Job & Career Management</h1><div class="muted">Publish verified opportunities with official source links.</div></div>
        <a class="btn" href="{{ route('admin.dashboard') }}">Dashboard</a>
    </div>

    @if(session('success'))<div class="notice ok">{{ session('success') }}</div>@endif
    @if($errors->any())
        <div class="notice err"><ul style="margin:0;padding-left:18px">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
    @endif

    <div class="grid">
        <div class="card">
            <h2 style="margin-top:0">Add Job Listing</h2>
            <form method="POST" action="{{ route('admin.jobs.store') }}">
                @csrf
                <div class="field"><label>Title<input name="title" required value="{{ old('title') }}"></label></div>
                <div class="field"><label>Organization<input name="organization" required value="{{ old('organization') }}"></label></div>
                <div class="row">
                    <div class="field"><label>Type<select name="job_type" required>@foreach(['government','private','internship','apprenticeship'] as $type)<option value="{{ $type }}" @selected(old('job_type')===$type)>{{ ucfirst($type) }}</option>@endforeach</select></label></div>
                    <div class="field"><label>Category<input name="category" value="{{ old('category') }}"></label></div>
                </div>
                <div class="row">
                    <div class="field"><label>Qualification<input name="qualification" value="{{ old('qualification') }}"></label></div>
                    <div class="field"><label>Location<input name="location" value="{{ old('location') }}"></label></div>
                </div>
                <div class="row">
                    <div class="field"><label>Published Date<input type="date" name="published_date" value="{{ old('published_date') }}"></label></div>
                    <div class="field"><label>Last Date<input type="date" name="last_date" value="{{ old('last_date') }}"></label></div>
                </div>
                <div class="field"><label>Branch<select name="branch_id"><option value="">Global / All Branches</option>@foreach($branches as $branch)<option value="{{ $branch->id }}" @selected((string)old('branch_id')===(string)$branch->id)>{{ $branch->name }}</option>@endforeach</select></label></div>
                <div class="field"><label>Official Apply / Details URL<input type="url" name="official_url" required value="{{ old('official_url') }}"></label></div>
                <div class="field"><label>Summary<textarea name="summary" rows="5">{{ old('summary') }}</textarea></label></div>
                <div class="field"><label><input style="width:auto" type="checkbox" name="is_featured" value="1" @checked(old('is_featured'))> Featured</label></div>
                <div class="field"><label><input style="width:auto" type="checkbox" name="status" value="1" checked> Publish now</label></div>
                <button class="btn" type="submit">Save Job</button>
            </form>
        </div>

        <div class="card">
            <form method="GET" style="display:flex;gap:8px;margin-bottom:14px;flex-wrap:wrap">
                <input style="max-width:320px" name="q" value="{{ request('q') }}" placeholder="Search jobs">
                <select style="max-width:180px" name="type"><option value="">All Types</option>@foreach(['government','private','internship','apprenticeship'] as $type)<option value="{{ $type }}" @selected(request('type')===$type)>{{ ucfirst($type) }}</option>@endforeach</select>
                <button class="btn" type="submit">Filter</button>
            </form>
            <div style="overflow:auto">
                <table class="table">
                    <thead><tr><th>Job</th><th>Type</th><th>Last Date</th><th>Status</th><th>Official</th></tr></thead>
                    <tbody>
                    @forelse($jobs as $job)
                        <tr>
                            <td><strong>{{ $job->title }}</strong><div class="muted">{{ $job->organization }}</div><div class="muted">{{ $job->location ?: '—' }}</div></td>
                            <td>{{ ucfirst($job->job_type) }}</td>
                            <td>{{ $job->last_date?->format('d M Y') ?? '—' }}</td>
                            <td>{{ $job->status ? 'Published' : 'Hidden' }}</td>
                            <td><a href="{{ $job->official_url }}" target="_blank" rel="noopener noreferrer">Open</a></td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="muted">No jobs added yet.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
            <div style="margin-top:14px">{{ $jobs->links() }}</div>
        </div>
    </div>
</div>
</body>
</html>
