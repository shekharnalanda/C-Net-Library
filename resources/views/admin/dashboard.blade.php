<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Admin Dashboard | C-Net Library</title>
    <style>
        body { margin:0; font-family:Arial,sans-serif; background:#f6f7f9; color:#111827; }
        header { background:#111827; color:white; padding:18px 24px; display:flex; justify-content:space-between; align-items:center; }
        main { padding:24px; max-width:1200px; margin:auto; }
        .cards { display:grid; grid-template-columns:repeat(auto-fit,minmax(180px,1fr)); gap:16px; }
        .card { background:white; border-radius:12px; padding:20px; box-shadow:0 5px 20px rgba(0,0,0,.05); }
        .label { color:#667085; font-size:14px; }
        .value { font-size:30px; font-weight:700; margin-top:8px; }
        .muted { color:#667085; font-size:12px; margin-top:5px; }
        .actions { margin-top:24px; display:flex; gap:12px; flex-wrap:wrap; }
        a, button { text-decoration:none; border:0; border-radius:8px; padding:10px 14px; font-weight:700; cursor:pointer; }
        a { background:#e5e7eb; color:#111827; }
        button { background:#111827; color:white; }
        form { margin:0; }
    </style>
</head>
<body>
<header>
    <div><strong>C-Net Library</strong> — Admin Dashboard</div>
    <form method="POST" action="{{ route('logout') }}">
        @csrf
        <button type="submit">Logout</button>
    </form>
</header>
<main>
    <div class="cards">
        <div class="card"><div class="label">Active Students</div><div class="value">{{ $data['active_students'] }}</div></div>
        <div class="card"><div class="label">Total Seats</div><div class="value">{{ $data['total_seats'] }}</div></div>
        <div class="card"><div class="label">Today's Membership Income</div><div class="value">₹{{ number_format($data['today_membership_income'], 2) }}</div><div class="muted">Gross ₹{{ number_format($data['today_gross_collection'], 2) }} · Adjustments ₹{{ number_format($data['today_adjustments'], 2) }}</div></div>
        <div class="card"><div class="label">Today's Library Recoveries</div><div class="value">₹{{ number_format($data['today_library_income'], 2) }}</div><div class="muted">Fine and lost-book collections</div></div>
        <div class="card"><div class="label">Today's Total Income</div><div class="value">₹{{ number_format($data['today_total_income'], 2) }}</div><div class="muted">Membership + library income</div></div>
        <div class="card"><div class="label">Today's Net Expenses</div><div class="value">₹{{ number_format($data['today_expenses'], 2) }}</div><div class="muted">Gross ₹{{ number_format($data['today_gross_expenses'], 2) }} · Adjustments ₹{{ number_format($data['today_expense_adjustments'], 2) }}</div></div>
        <div class="card"><div class="label">Today's Cash Position</div><div class="value">₹{{ number_format($data['today_cash_position'], 2) }}</div><div class="muted">Total income − net expenses</div></div>
        <div class="card"><div class="label">Pending Admissions</div><div class="value">{{ $data['pending_admissions'] }}</div></div>
        <div class="card"><div class="label">Renewals Due (7 Days)</div><div class="value">{{ $data['renewals_due'] }}</div></div>
    </div>

    <div class="actions">
        <a href="{{ route('admin.admissions.index') }}">Manage Admissions</a>
        <a href="{{ route('admin.reports.index') }}">Reports</a>
        <a href="{{ route('admin.expenses.index') }}">Cashbook</a>
        <a href="{{ route('admission.create') }}" target="_blank" rel="noopener">Open Public Admission Form</a>
    </div>
</main>
</body>
</html>
