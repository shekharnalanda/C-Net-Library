<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admissions - C-Net Library</title>
    <style>
        *{box-sizing:border-box}body{font-family:Arial,sans-serif;background:#f5f7fb;margin:0;color:#172033}.shell{display:grid;grid-template-columns:240px 1fr;min-height:100vh}.side{background:#111827;color:#fff;padding:24px 18px}.brand{font-weight:800;font-size:20px;margin-bottom:24px}.side a{display:block;color:#d1d5db;text-decoration:none;padding:10px 12px;border-radius:8px;margin:4px 0}.side a:hover,.side a.active{background:#1f2937;color:#fff}.main{padding:28px}.top{display:flex;justify-content:space-between;align-items:flex-start;gap:16px;margin-bottom:22px}.muted{color:#6b7280}.summary{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:14px;margin-bottom:18px}.stat,.card{background:#fff;border:1px solid #e5e7eb;border-radius:14px;box-shadow:0 6px 22px rgba(15,23,42,.05)}.stat{padding:16px}.stat b{display:block;font-size:28px;margin-top:5px}.card{padding:18px}.filters{display:flex;gap:10px;flex-wrap:wrap;margin-bottom:16px}.filters input,.filters select,.filters button,.btn{padding:10px 12px;border:1px solid #d1d5db;border-radius:9px;font-size:14px}.filters input{min-width:260px}.filters button,.btn{background:#111827;color:#fff;text-decoration:none;cursor:pointer}.btn.light{background:#fff;color:#111827}.table-wrap{overflow:auto}.table{width:100%;border-collapse:collapse}.table th,.table td{padding:12px;border-bottom:1px solid #edf0f4;text-align:left;font-size:14px}.badge{display:inline-block;padding:4px 8px;border-radius:999px;font-size:12px;background:#eef2ff}.badge.pending{background:#fff7ed;color:#9a3412}.badge.approved{background:#ecfdf5;color:#047857}.badge.rejected{background:#fef2f2;color:#b91c1c}.pagination{margin-top:18px}@media(max-width:900px){.shell{grid-template-columns:1fr}.side{display:none}.main{padding:18px}.summary{grid-template-columns:repeat(2,minmax(0,1fr))}.top{flex-direction:column}.filters input{min-width:100%}}
    </style>
</head>
<body>
<div class="shell">
    <aside class="side">
        <div class="brand">C-Net Library</div>
        <a href="{{ route('admin.dashboard') }}">Dashboard</a>
        <a class="active" href="{{ route('admin.admissions.index') }}">Admissions</a>
        @if(auth()->user()->canAccess('students.manage'))<a href="{{ route('admin.students.index') }}">Students</a>@endif
        @if(auth()->user()->canAccess('attendance.manage'))<a href="{{ route('admin.attendance.index') }}">Attendance</a>@endif
        @if(auth()->user()->canAccess('payments.manage'))<a href="{{ route('admin.expenses.index') }}">Cashbook</a>@endif
        @if(auth()->user()->canAccess('library.manage'))<a href="{{ route('admin.library.index') }}">Library</a>@endif
        @if(auth()->user()->canAccess('reports.view'))<a href="{{ route('admin.reports.index') }}">Reports</a>@endif
    </aside>
    <main class="main">
        <div class="top">
            <div>
                <h1 style="margin:0 0 6px">Admissions</h1>
                <div class="muted">Review applications and convert approved applicants into student memberships.</div>
            </div>
            <a class="btn light" href="{{ route('admission.create') }}" target="_blank" rel="noopener">Open Public Admission Form</a>
        </div>

        @if(session('success'))<div class="card" style="margin-bottom:16px;border-color:#a7f3d0;background:#ecfdf5">{{ session('success') }}</div>@endif

        <div class="summary">
            <div class="stat"><span class="muted">Total</span><b>{{ $summary['total'] }}</b></div>
            <div class="stat"><span class="muted">Pending</span><b>{{ $summary['pending'] }}</b></div>
            <div class="stat"><span class="muted">Approved</span><b>{{ $summary['approved'] }}</b></div>
            <div class="stat"><span class="muted">Rejected</span><b>{{ $summary['rejected'] }}</b></div>
        </div>

        <div class="card">
            <form method="GET" class="filters">
                <input type="text" name="search" value="{{ request('search') }}" placeholder="Application no, name, mobile or email">
                <select name="status">
                    <option value="">All status</option>
                    <option value="pending" @selected(request('status') === 'pending')>Pending</option>
                    <option value="approved" @selected(request('status') === 'approved')>Approved</option>
                    <option value="rejected" @selected(request('status') === 'rejected')>Rejected</option>
                </select>
                <button type="submit">Filter</button>
                @if(request()->hasAny(['search','status']))<a class="btn light" href="{{ route('admin.admissions.index') }}">Reset</a>@endif
            </form>

            <div class="table-wrap">
                <table class="table">
                    <thead><tr><th>Application</th><th>Student</th><th>Mobile</th><th>Branch</th><th>Slot / Plan</th><th>Status</th><th></th></tr></thead>
                    <tbody>
                    @forelse($admissions as $admission)
                        <tr>
                            <td><strong>{{ $admission->application_no }}</strong><div class="muted">{{ optional($admission->created_at)->format('d M Y') }}</div></td>
                            <td>{{ $admission->name }}<div class="muted">{{ $admission->email ?: '—' }}</div></td>
                            <td>{{ $admission->mobile }}</td>
                            <td>{{ $admission->branch?->name ?? '—' }}</td>
                            <td>{{ $admission->studySlot?->name ?? '—' }}<div class="muted">{{ $admission->feePlan?->name ?? 'No plan selected' }}</div></td>
                            <td><span class="badge {{ $admission->status }}">{{ ucfirst(str_replace('_',' ',$admission->status)) }}</span></td>
                            <td><a class="btn" href="{{ route('admin.admissions.show', $admission) }}">Review</a></td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="muted" style="text-align:center;padding:36px">No admission applications found.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
            <div class="pagination">{{ $admissions->links() }}</div>
        </div>
    </main>
</div>
</body>
</html>
