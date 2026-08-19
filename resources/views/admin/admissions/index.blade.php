<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admissions - C-Net Library</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<div class="container py-5">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-1">Admissions</h1>
            <p class="text-muted mb-0">Review and convert applications into student memberships.</p>
        </div>
        <a href="{{ route('home') }}" class="btn btn-outline-secondary">Home</a>
    </div>

    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    <div class="card border-0 shadow-sm">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                <tr>
                    <th>Application</th>
                    <th>Student</th>
                    <th>Mobile</th>
                    <th>Branch</th>
                    <th>Slot</th>
                    <th>Status</th>
                    <th class="text-end">Action</th>
                </tr>
                </thead>
                <tbody>
                @forelse($admissions as $admission)
                    <tr>
                        <td>{{ $admission->application_no }}</td>
                        <td>{{ $admission->name }}</td>
                        <td>{{ $admission->mobile }}</td>
                        <td>{{ $admission->branch?->name ?? '—' }}</td>
                        <td>{{ $admission->studySlot?->name ?? '—' }}</td>
                        <td><span class="badge text-bg-secondary">{{ str_replace('_', ' ', ucfirst($admission->status)) }}</span></td>
                        <td class="text-end">
                            <a href="{{ route('admin.admissions.show', $admission) }}" class="btn btn-sm btn-primary">Review</a>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="text-center py-5 text-muted">No admission applications yet.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-4">{{ $admissions->links() }}</div>
</div>
</body>
</html>
