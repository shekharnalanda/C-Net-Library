<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
    <title>Bulk Student ID Cards - C-Net Library</title>
    <style>
        *{box-sizing:border-box}body{font-family:Arial,sans-serif;background:#f5f7fb;margin:0;color:#1f2937}.wrap{max-width:1180px;margin:28px auto;padding:0 18px}.top{display:flex;justify-content:space-between;align-items:flex-start;gap:16px;margin-bottom:20px}.top h1{margin:0 0 5px}.muted{color:#6b7280}.actions{display:flex;gap:8px;flex-wrap:wrap}.btn{display:inline-block;padding:10px 14px;border:0;border-radius:9px;background:#102a43;color:#fff;text-decoration:none;font-weight:700;cursor:pointer}.btn.alt{background:#0f766e}.btn.light{background:#e5e7eb;color:#111827}.card{background:#fff;border:1px solid #e5e7eb;border-radius:14px;padding:18px;box-shadow:0 6px 22px rgba(15,23,42,.05)}.filter{display:grid;grid-template-columns:1fr auto auto;gap:10px;margin-bottom:18px}input,button{padding:10px 12px;border:1px solid #d1d5db;border-radius:9px;font-size:14px}.table-wrap{overflow:auto}.table{width:100%;border-collapse:collapse;min-width:760px}.table th,.table td{padding:12px;border-bottom:1px solid #edf0f4;text-align:left;font-size:14px}.table th{font-size:12px;text-transform:uppercase;color:#6b7280}.photo{width:38px;height:46px;object-fit:cover;border-radius:6px;background:#eef2f5}.placeholder{width:38px;height:46px;border-radius:6px;background:#eef2f5;display:flex;align-items:center;justify-content:center;font-size:8px;text-align:center;color:#6b7280}.student{display:flex;align-items:center;gap:10px}.sub{font-size:12px;color:#6b7280;margin-top:3px}.selection{display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap;margin:0 0 14px}.bottom{display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap;margin-top:16px}.error{background:#fef2f2;border:1px solid #fca5a5;padding:12px;border-radius:9px;margin-bottom:15px}@media(max-width:700px){.top{flex-direction:column}.filter{grid-template-columns:1fr}.wrap{padding:0 12px}}
    </style>
</head>
<body><div class="wrap">
    <div class="top"><div><h1>Bulk Student ID Cards</h1><div class="muted">Search active students, select up to 100, then print two students per A4 sheet with Front and Back.</div></div><div class="actions"><a class="btn light" href="{{ route('admin.students.index') }}">Students</a><a class="btn" href="{{ route('admin.dashboard') }}">Dashboard</a></div></div>
    @if($errors->any())<div class="error">{{ $errors->first() }}</div>@endif
    <div class="card">
        <form method="GET" action="{{ route('admin.students.id-cards.bulk') }}" class="filter"><input type="search" name="search" value="{{ $search }}" placeholder="Student name, code or mobile"><button class="btn" type="submit">Search</button>@if($search !== '')<a class="btn light" href="{{ route('admin.students.id-cards.bulk') }}">Clear</a>@endif</form>
        <form method="POST" action="{{ route('admin.students.id-cards.bulk.print') }}" target="_blank">@csrf
            <div class="selection"><div><button class="btn alt" type="button" id="select-all">Select All</button> <button class="btn light" type="button" id="clear-all">Clear Selection</button></div><div class="muted">Maximum 100 active students per batch</div></div>
            <div class="table-wrap"><table class="table"><thead><tr><th style="width:52px"><input id="master" type="checkbox" aria-label="Select all students"></th><th>Student</th><th>Branch</th><th>Membership</th><th>Valid Until</th></tr></thead><tbody>
            @forelse($students as $student)
                <tr><td><input class="student-check" type="checkbox" name="students[]" value="{{ $student->id }}"></td><td><div class="student">@if($student->photo)<img class="photo" src="{{ asset('storage/'.$student->photo) }}" alt="">@else<div class="placeholder">NO<br>PHOTO</div>@endif<div><strong>{{ $student->name }}</strong><div class="sub">{{ $student->student_code }} · {{ $student->mobile }}</div></div></div></td><td>{{ $student->branch?->name ?? '—' }}</td><td>{{ $student->activeMembership?->feePlan?->name ?? 'No active plan' }}<div class="sub">{{ $student->activeMembership?->studySlot?->name ?? 'No slot' }}</div></td><td>{{ $student->activeMembership?->expiry_date?->format('d M Y') ?? '—' }}</td></tr>
            @empty<tr><td colspan="5" class="muted" style="text-align:center;padding:28px">No active students found.</td></tr>@endforelse
            </tbody></table></div>
            <div class="bottom"><strong id="selected-count">0 selected</strong><button class="btn alt" id="print-btn" type="submit" disabled>Print Selected ID Cards</button></div>
        </form>
    </div>
</div>
<script>
document.addEventListener('DOMContentLoaded',function(){
    const checks=[...document.querySelectorAll('.student-check')],master=document.getElementById('master'),count=document.getElementById('selected-count'),print=document.getElementById('print-btn');
    function sync(){const n=checks.filter(x=>x.checked).length;count.textContent=n+' selected';print.disabled=n===0;print.textContent=n?('Print '+n+' Student'+(n===1?'':'s')+' ID Cards'):'Print Selected ID Cards';master.checked=checks.length>0&&n===checks.length;master.indeterminate=n>0&&n<checks.length}
    function setAll(v){checks.forEach(x=>x.checked=v);sync()}
    checks.forEach(x=>x.addEventListener('change',sync));master.addEventListener('change',()=>setAll(master.checked));document.getElementById('select-all').addEventListener('click',()=>setAll(true));document.getElementById('clear-all').addEventListener('click',()=>setAll(false));sync();
});
</script></body></html>
