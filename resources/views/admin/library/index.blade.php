<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Physical Library - C-Net Library</title>
    <style>
        body{font-family:Arial,sans-serif;background:#f5f7fb;margin:0;color:#1f2937}.wrap{max-width:1200px;margin:30px auto;padding:0 18px}.top{display:flex;justify-content:space-between;gap:12px;align-items:center}.card{background:#fff;border:1px solid #e5e7eb;border-radius:14px;padding:18px;margin-top:18px;box-shadow:0 6px 22px rgba(15,23,42,.05)}.table{width:100%;border-collapse:collapse}.table th,.table td{padding:10px;border-bottom:1px solid #edf0f4;text-align:left;font-size:14px}.btn{display:inline-block;background:#111827;color:#fff;border:0;border-radius:8px;padding:9px 12px;text-decoration:none;cursor:pointer}.btn.green{background:#047857}.btn.blue{background:#2563eb}input,select{padding:9px;border:1px solid #d1d5db;border-radius:8px}.muted{color:#6b7280}.grid{display:grid;grid-template-columns:1fr 1fr;gap:18px}@media(max-width:800px){.grid{grid-template-columns:1fr}.top{align-items:flex-start;flex-direction:column}}
    </style>
</head>
<body>
<div class="wrap">
    <div class="top">
        <div><h1 style="margin:0">Physical Library</h1><div class="muted">Books, copies, issue/return and fines</div></div>
        <a class="btn" href="{{ route('admin.dashboard') }}">Dashboard</a>
    </div>

    @if(session('success'))<div class="card" style="background:#f0fdf4;border-color:#86efac">{{ session('success') }}</div>@endif
    @if($errors->any())<div class="card" style="background:#fef2f2;border-color:#fca5a5"><ul style="margin:0;padding-left:18px">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif

    <div class="grid">
        <div class="card">
            <h2 style="margin-top:0">Issue Book</h2>
            <form method="POST" action="{{ route('admin.library.issue') }}">
                @csrf
                <div style="margin-bottom:12px">
                    <label>Student</label><br>
                    <select name="student_id" required style="width:100%">
                        <option value="">Select student</option>
                        @foreach($students as $student)<option value="{{ $student->id }}">{{ $student->student_code }} - {{ $student->name }}</option>@endforeach
                    </select>
                </div>
                <div style="margin-bottom:12px">
                    <label>Available Copy</label><br>
                    <select name="book_copy_id" required style="width:100%">
                        <option value="">Select book copy</option>
                        @foreach($copies->where('status','available') as $copy)
                            <option value="{{ $copy->id }}">{{ $copy->book?->title }} — {{ $copy->accession_no }}{{ $copy->rack_no ? ' / Rack '.$copy->rack_no : '' }}</option>
                        @endforeach
                    </select>
                </div>
                <div style="margin-bottom:12px"><label>Issue Days</label><br><input type="number" name="issue_days" value="14" min="1" max="90" style="width:100%;box-sizing:border-box"></div>
                <button class="btn blue" type="submit">Issue Book</button>
            </form>
        </div>

        <div class="card">
            <h2 style="margin-top:0">Search Catalogue</h2>
            <form method="GET" action="{{ route('admin.library.index') }}">
                <input type="text" name="search" value="{{ request('search') }}" placeholder="Title, author, accession, barcode" style="width:100%;box-sizing:border-box">
                <button class="btn" type="submit" style="margin-top:10px">Search</button>
            </form>
            <div style="margin-top:16px" class="muted">Total visible copies: {{ $copies->total() }}</div>
        </div>
    </div>

    <div class="card">
        <h2 style="margin-top:0">Currently Issued</h2>
        <div style="overflow:auto">
            <table class="table">
                <thead><tr><th>Book</th><th>Student</th><th>Issued</th><th>Due</th><th>Status</th><th>Action</th></tr></thead>
                <tbody>
                @forelse($issues as $issue)
                    <tr>
                        <td>{{ $issue->bookCopy?->book?->title }}<br><span class="muted">{{ $issue->bookCopy?->accession_no }}</span></td>
                        <td>{{ $issue->student?->name }}<br><span class="muted">{{ $issue->student?->student_code }}</span></td>
                        <td>{{ $issue->issued_at?->format('d M Y') }}</td>
                        <td>{{ $issue->due_at?->format('d M Y') }}</td>
                        <td>{{ $issue->due_at && $issue->due_at->lt(today()) ? 'Overdue' : ucfirst($issue->status) }}</td>
                        <td>
                            <form method="POST" action="{{ route('admin.library.return', $issue) }}">@csrf<button class="btn green" type="submit">Return</button></form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="muted">No books currently issued.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="card">
        <h2 style="margin-top:0">Book Copies</h2>
        <div style="overflow:auto">
            <table class="table">
                <thead><tr><th>Accession</th><th>Book</th><th>Author</th><th>Branch</th><th>Rack</th><th>Condition</th><th>Status</th></tr></thead>
                <tbody>
                @foreach($copies as $copy)
                    <tr>
                        <td>{{ $copy->accession_no }}</td>
                        <td>{{ $copy->book?->title }}</td>
                        <td>{{ $copy->book?->author ?: '—' }}</td>
                        <td>{{ $copy->branch?->name }}</td>
                        <td>{{ $copy->rack_no ?: '—' }}</td>
                        <td>{{ ucfirst($copy->condition) }}</td>
                        <td>{{ ucfirst($copy->status) }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
        <div style="margin-top:14px">{{ $copies->links() }}</div>
    </div>
</div>
</body>
</html>
