<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports & Analytics - C-Net Library</title>
    <style>
        body{font-family:Arial,sans-serif;background:#f5f7fb;margin:0;color:#1f2937}.wrap{max-width:1200px;margin:30px auto;padding:0 18px}.top{display:flex;justify-content:space-between;gap:16px;align-items:center;margin-bottom:20px}.grid{display:grid;grid-template-columns:repeat(4,1fr);gap:14px}.card{background:#fff;border:1px solid #e5e7eb;border-radius:14px;padding:18px;box-shadow:0 6px 22px rgba(15,23,42,.05)}.label{font-size:12px;color:#6b7280;text-transform:uppercase}.metric{font-size:28px;font-weight:700;margin-top:6px}.muted{color:#6b7280}.filters{display:flex;gap:10px;flex-wrap:wrap;align-items:end;margin-bottom:18px}.field label{display:block;font-size:12px;color:#6b7280;margin-bottom:5px}.field input,.field select{padding:9px;border:1px solid #d1d5db;border-radius:8px;min-width:170px}.btn{background:#111827;color:white;border:0;border-radius:8px;padding:10px 14px;cursor:pointer;text-decoration:none}.table{width:100%;border-collapse:collapse}.table th,.table td{padding:10px;border-bottom:1px solid #edf0f4;text-align:left;font-size:14px}.section{margin-top:18px}.income-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:10px}@media(max-width:900px){.grid{grid-template-columns:repeat(2,1fr)}}@media(max-width:560px){.grid{grid-template-columns:1fr}.top{align-items:flex-start;flex-direction:column}}
    </style>
</head>
<body>
<div class="wrap">
    <div class="top">
        <div><h1 style="margin:0">Reports & Analytics</h1><div class="muted">Operational and cash reconciliation snapshot for C-Net Library</div></div>
        <div><a class="btn" href="{{ route('admin.expenses.index') }}">Cashbook</a> <a class="btn" href="{{ route('admin.dashboard') }}">Back to Dashboard</a></div>
    </div>

    <form method="GET" class="card filters">
        <div class="field"><label>From</label><input type="date" name="from" value="{{ $from->toDateString() }}"></div>
        <div class="field"><label>To</label><input type="date" name="to" value="{{ $to->toDateString() }}"></div>
        <div class="field"><label>Branch</label><select name="branch_id"><option value="">All Branches</option>@foreach($branches as $branch)<option value="{{ $branch->id }}" @selected($branchId == $branch->id)>{{ $branch->name }}</option>@endforeach</select></div>
        <button class="btn" type="submit">Apply Filter</button>
    </form>

    <div class="grid">
        <div class="card"><div class="label">Active Students</div><div class="metric">{{ number_format($metrics['students']) }}</div></div>
        <div class="card"><div class="label">Active Memberships</div><div class="metric">{{ number_format($metrics['active_memberships']) }}</div></div>
        <div class="card"><div class="label">Membership Income</div><div class="metric">₹{{ number_format($metrics['membership_income'], 2) }}</div><div class="muted">Gross ₹{{ number_format($metrics['gross_collection'], 2) }} · Adjustments ₹{{ number_format($metrics['adjustments'], 2) }}</div></div>
        <div class="card"><div class="label">Library Recoveries</div><div class="metric">₹{{ number_format($metrics['library_income'], 2) }}</div><div class="muted">Fines ₹{{ number_format($metrics['library_fine_income'], 2) }} · Lost books ₹{{ number_format($metrics['library_loss_income'], 2) }}</div></div>
        <div class="card"><div class="label">Total Income</div><div class="metric">₹{{ number_format($metrics['total_income'], 2) }}</div><div class="muted">Membership + library recoveries</div></div>
        <div class="card"><div class="label">Net Operating Expenses</div><div class="metric">₹{{ number_format($metrics['expenses'], 2) }}</div><div class="muted">Gross ₹{{ number_format($metrics['gross_expenses'], 2) }} · Adjustments ₹{{ number_format($metrics['expense_adjustments'], 2) }}</div></div>
        <div class="card"><div class="label">Closing Cash Position</div><div class="metric">₹{{ number_format($metrics['closing_balance'], 2) }}</div><div class="muted">Total income − net expenses</div></div>
        <div class="card"><div class="label">Current Membership Due</div><div class="metric">₹{{ number_format($metrics['due'], 2) }}</div></div>
        <div class="card"><div class="label">Seat Occupancy</div><div class="metric">{{ $metrics['seat_occupancy_percent'] }}%</div><div class="muted">{{ $metrics['occupied_seats'] }} / {{ $metrics['total_seats'] }} seats</div></div>
        <div class="card"><div class="label">Study Hours</div><div class="metric">{{ number_format($metrics['study_hours'], 1) }}</div><div class="muted">Selected period</div></div>
        <div class="card"><div class="label">Admissions</div><div class="metric">{{ number_format($metrics['admissions']) }}</div><div class="muted">Conversion {{ $metrics['admission_conversion_percent'] }}%</div></div>
        <div class="card"><div class="label">CRM Enquiries</div><div class="metric">{{ number_format($metrics['enquiries']) }}</div><div class="muted">Conversion {{ $metrics['crm_conversion_percent'] }}%</div></div>
        <div class="card"><div class="label">Books Available</div><div class="metric">{{ number_format($metrics['books_available']) }}</div></div>
        <div class="card"><div class="label">Books Issued</div><div class="metric">{{ number_format($metrics['books_issued']) }}</div></div>
        <div class="card"><div class="label">Overdue Books</div><div class="metric">{{ number_format($metrics['overdue_books']) }}</div></div>
    </div>

    <div class="card section">
        <h2 style="margin-top:0">Income by Category</h2>
        <div class="income-grid">
            @forelse($incomeCategories as $row)
                <div><div class="label">{{ $row->category }}</div><div class="metric" style="font-size:22px">₹{{ number_format((float)$row->total, 2) }}</div></div>
            @empty
                <div class="muted">No income recorded in this period.</div>
            @endforelse
        </div>
    </div>

    <div class="card section">
        <h2 style="margin-top:0">Daily Cash Position</h2>
        <div style="overflow:auto">
            <table class="table">
                <thead><tr><th>Date</th><th>Gross Fees</th><th>Fee Adjustments</th><th>Membership Income</th><th>Library Income</th><th>Total Income</th><th>Net Expenses</th><th>Cash Position</th></tr></thead>
                <tbody>
                @forelse($dailyCollections as $row)
                    <tr>
                        <td>{{ \Carbon\Carbon::parse($row->payment_date)->format('d M Y') }}</td>
                        <td>₹{{ number_format((float)$row->gross_total, 2) }}</td>
                        <td>₹{{ number_format((float)$row->adjustment_total, 2) }}</td>
                        <td>₹{{ number_format((float)$row->membership_total, 2) }}</td>
                        <td>₹{{ number_format((float)$row->library_total, 2) }}</td>
                        <td><strong>₹{{ number_format((float)$row->total, 2) }}</strong></td>
                        <td>₹{{ number_format((float)$row->expense_total, 2) }}</td>
                        <td><strong>₹{{ number_format((float)$row->cash_balance, 2) }}</strong></td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="muted">No finance activity recorded in this period.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
</body>
</html>
