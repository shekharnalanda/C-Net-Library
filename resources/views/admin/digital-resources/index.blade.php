<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Digital Library - C-Net Library</title>
    <style>
        body{font-family:Arial,sans-serif;background:#f5f7fb;margin:0;color:#1f2937}.wrap{max-width:1180px;margin:30px auto;padding:0 18px}.top{display:flex;justify-content:space-between;align-items:center;gap:12px;margin-bottom:18px}.grid{display:grid;grid-template-columns:1fr 2fr;gap:18px}.card{background:#fff;border:1px solid #e5e7eb;border-radius:14px;padding:18px;box-shadow:0 6px 22px rgba(15,23,42,.05)}.btn{display:inline-block;background:#111827;color:#fff;border:0;border-radius:9px;padding:10px 13px;text-decoration:none;cursor:pointer}.field{margin-bottom:12px}input,select,textarea{width:100%;box-sizing:border-box;padding:10px;border:1px solid #d1d5db;border-radius:9px;margin-top:5px}.table{width:100%;border-collapse:collapse}.table th,.table td{padding:10px;border-bottom:1px solid #edf0f4;text-align:left;font-size:14px}.muted{color:#6b7280}.badge{display:inline-block;padding:4px 8px;border-radius:999px;background:#eef2ff;font-size:12px}@media(max-width:850px){.grid{grid-template-columns:1fr}.top{align-items:flex-start;flex-direction:column}}
    </style>
</head>
<body>
<div class="wrap">
    <div class="top">
        <div><h1 style="margin:0">Digital Library</h1><div class="muted">Manage PDFs, ebooks, notes, question papers, videos and links.</div></div>
        <a class="btn" href="{{ route('admin.dashboard') }}">Dashboard</a>
    </div>

    @if(session('success'))<div class="card" style="margin-bottom:18px;border-color:#86efac;background:#f0fdf4">{{ session('success') }}</div>@endif
    @if($errors->any())<div class="card" style="margin-bottom:18px;border-color:#fca5a5;background:#fef2f2"><ul style="margin:0;padding-left:18px">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif

    <div class="grid">
        <div class="card">
            <h2 style="margin-top:0">Add Resource</h2>
            <form method="POST" action="{{ route('admin.digital-resources.store') }}">
                @csrf
                <div class="field"><label>Title<input name="title" required value="{{ old('title') }}"></label></div>
                <div class="field"><label>Branch<select name="branch_id"><option value="">All / Global</option>@foreach($branches as $branch)<option value="{{ $branch->id }}">{{ $branch->name }}</option>@endforeach</select></label></div>
                <div class="field"><label>Type<select name="resource_type" required><option value="pdf">PDF</option><option value="ebook">Ebook</option><option value="notes">Notes</option><option value="question_paper">Question Paper</option><option value="video">Video</option><option value="link">External Link</option></select></label></div>
                <div class="field"><label>Category<input name="category" value="{{ old('category') }}"></label></div>
                <div class="field"><label>Description<textarea name="description" rows="3">{{ old('description') }}</textarea></label></div>
                <div class="field"><label>Private File Path<input name="file_path" placeholder="students/resources/file.pdf" value="{{ old('file_path') }}"></label></div>
                <div class="field"><label>External URL<input name="external_url" type="url" value="{{ old('external_url') }}"></label></div>
                <div class="field"><label>Access<select name="access_type" required><option value="public">Public</option><option value="members" selected>Members</option><option value="premium">Premium</option></select></label></div>
                <div class="field"><label><input type="checkbox" name="download_allowed" value="1" checked style="width:auto"> Download allowed</label></div>
                <button class="btn" type="submit">Add Resource</button>
            </form>
        </div>

        <div class="card">
            <form method="GET" style="display:flex;gap:8px;margin-bottom:14px"><input name="search" value="{{ request('search') }}" placeholder="Search title, category or type"><button class="btn" type="submit">Search</button></form>
            <div style="overflow:auto">
                <table class="table">
                    <thead><tr><th>Title</th><th>Type</th><th>Access</th><th>Branch</th><th>Usage</th><th>Status</th></tr></thead>
                    <tbody>
                    @forelse($resources as $resource)
                        <tr>
                            <td><strong>{{ $resource->title }}</strong><div class="muted">{{ $resource->category ?: 'Uncategorized' }}</div></td>
                            <td><span class="badge">{{ strtoupper(str_replace('_',' ', $resource->resource_type)) }}</span></td>
                            <td>{{ ucfirst($resource->access_type) }}</td>
                            <td>{{ $resource->branch?->name ?? 'Global' }}</td>
                            <td>{{ $resource->logs_count }}</td>
                            <td>{{ $resource->status ? 'Active' : 'Inactive' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="muted">No digital resources found.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
            <div style="margin-top:14px">{{ $resources->links() }}</div>
        </div>
    </div>
</div>
</body>
</html>
