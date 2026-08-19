<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Students - C-Net Library</title>
    <style>
        body{font-family:Arial,sans-serif;background:#f5f7fb;margin:0;color:#1f2937}.wrap{max-width:1180px;margin:32px auto;padding:0 18px}.top{display:flex;justify-content:space-between;align-items:center;gap:16px;margin-bottom:22px}.card{background:#fff;border:1px solid #e5e7eb;border-radius:14px;padding:18px;box-shadow:0 6px 22px rgba(15,23,42,.05)}.filters{display:flex;gap:10px;flex-wrap:wrap;margin-bottom:16px}input,select,button,a.btn{padding:10px 12px;border:1px solid #d1d5db;border-radius:9px;font-size:14px}button,a.btn{background:#111827;color:#fff;text-decoration:none;cursor:pointer}.table{width:100%;border-collapse:collapse}.table th,.table td{padding:12px;border-bottom:1px solid #edf0f4;text-align:left;font-size:14px}.muted{color:#6b7280}.status{font-size:12px;padding:4px 8px;border-radius:999px;background:#eef2ff}.actions{display:flex;gap:8px}.link{color:#2563eb;text-decoration:none}.pagination{margin-top:18px}</style>
</head>
<body>
<div class="wrap">
    <div class="top">
        <div>
            <h1 style="margin:0">Students</h1>
            <div class="muted">Manage active and historical student records.</div>
        </div>
        <a class="btn" href="{{ route('admin.dashboard') }}">Dashboard</a>
    </div>

    <div class="card">
        <form method="GET" class="filters">
            <input type="text" name="search" value="{{ request('search') }}" placeholder="Name, mobile, student ID">
            <select name="status">
                <option value="">All status</option>
                <option value="active" @selected(request('status') === 'active')>Active</option>
                <option value="inactive" @selected(request('status') === 'inactive')>Inactive</option>
                <option value="blocked" @selected(request('status') === 'blocked')>Blocked</option>
            </select>
            <button type="submit">Filter</button>
        </form>

        <div style="overflow:auto">
            <table class="table">
                <thead>
                <tr>
                    <th>Student ID</th><th>Name</th><th>Mobile</th><th>Branch</th><th>Plan / Slot</th><th>Status</th><th></th>
                </tr>
                </thead>
                <tbody>
                @forelse($students as $student)
                    <tr>
                        <td>{{ $student->student_code }}</td>
                        <td>{{ $student->name }}</td>
                        <td>{{ $student->mobile }}</td>
                        <td>{{ $student->branch?->name ?? '—' }}</td>
                        <td>
                            {{ $student->activeMembership?->feePlan?->name ?? '—' }}
                            @if($student->activeMembership?->studySlot)
                                <div class="muted">{{ $student->activeMembership->studySlot->name }}</div>
                            @endif
                        </td>
                        <td><span class="status">{{ ucfirst($student->status) }}</span></td>
                        <td><a class="link" href="{{ route('admin.students.show', $student) }}">View</a></td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="muted">No students found.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>

        <div class="pagination">{{ $students->links() }}</div>
    </div>
</div>
</body>
</html>
