<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Admin Dashboard | C-Net Library</title>
    <style>
        *{box-sizing:border-box}body{margin:0;font-family:Inter,Arial,sans-serif;background:#f4f6f8;color:#172033}.shell{display:grid;grid-template-columns:260px 1fr;min-height:100vh}.sidebar{background:#111827;color:#fff;padding:22px 16px;position:sticky;top:0;height:100vh;overflow:auto}.brand{font-size:20px;font-weight:800;padding:4px 8px 18px;border-bottom:1px solid rgba(255,255,255,.12);margin-bottom:14px}.brand small{display:block;font-size:12px;font-weight:500;color:#aab3c2;margin-top:4px}.nav-section{font-size:11px;text-transform:uppercase;letter-spacing:.08em;color:#8f9bad;padding:14px 10px 6px}.nav a{display:block;color:#dce2ea;text-decoration:none;padding:10px 12px;border-radius:8px;margin:2px 0;font-size:14px}.nav a:hover,.nav a.active{background:#1f2937;color:#fff}.main{min-width:0}.topbar{background:#fff;border-bottom:1px solid #e5e7eb;padding:16px 24px;display:flex;align-items:center;justify-content:space-between;gap:16px;position:sticky;top:0;z-index:5}.user{font-size:14px;color:#5f6b7a}.logout{border:0;background:#111827;color:#fff;padding:9px 13px;border-radius:8px;font-weight:700;cursor:pointer}.content{padding:24px;max-width:1500px;margin:auto}.hero{display:flex;justify-content:space-between;align-items:end;gap:18px;margin-bottom:20px}.hero h1{margin:0;font-size:28px}.hero p{margin:6px 0 0;color:#667085}.quick{display:flex;gap:8px;flex-wrap:wrap}.quick a{text-decoration:none;background:#fff;border:1px solid #dbe1e8;color:#172033;padding:9px 12px;border-radius:8px;font-weight:700;font-size:13px}.cards{display:grid;grid-template-columns:repeat(auto-fit,minmax(190px,1fr));gap:14px}.card{background:#fff;border:1px solid #e7ebf0;border-radius:12px;padding:18px;box-shadow:0 3px 12px rgba(16,24,40,.04)}.label{color:#667085;font-size:13px}.value{font-size:28px;font-weight:800;margin-top:7px}.muted{color:#667085;font-size:12px;margin-top:5px;line-height:1.4}.section{margin-top:22px;background:#fff;border:1px solid #e7ebf0;border-radius:12px;padding:18px}.section h2{font-size:17px;margin:0 0 12px}.modules{display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:10px}.module{text-decoration:none;border:1px solid #e5e7eb;border-radius:10px;padding:14px;color:#172033;background:#fafbfc}.module strong{display:block;font-size:14px}.module span{display:block;color:#667085;font-size:12px;margin-top:4px}@media(max-width:900px){.shell{grid-template-columns:1fr}.sidebar{position:relative;height:auto}.topbar{position:relative}.content{padding:16px}.hero{align-items:flex-start;flex-direction:column}}
    </style>
</head>
<body>
<div class="shell">
    <aside class="sidebar">
        <div class="brand">C-Net Library<small>Administration</small></div>
        <nav class="nav">
            <div class="nav-section">Overview</div>
            <a class="active" href="{{ route('admin.dashboard') }}">Dashboard</a>

            <div class="nav-section">Operations</div>
            @if(auth()->user()->canAccess('admissions.manage'))<a href="{{ route('admin.admissions.index') }}">Admissions</a>@endif
            @if(auth()->user()->canAccess('enquiries.manage'))<a href="{{ route('admin.enquiries.index') }}">Enquiries / CRM</a>@endif
            @if(auth()->user()->canAccess('students.manage'))<a href="{{ route('admin.students.index') }}">Students</a><a href="{{ route('admin.seats.available') }}">Available Seats</a>@endif
            @if(auth()->user()->canAccess('attendance.manage'))<a href="{{ route('admin.attendance.index') }}">Attendance</a><a href="{{ route('admin.attendance.scan') }}">QR Scan</a>@endif
            @if(auth()->user()->canAccess('payments.manage'))<a href="{{ route('admin.expenses.index') }}">Payments & Cashbook</a>@endif

            <div class="nav-section">Library & Content</div>
            @if(auth()->user()->canAccess('library.manage'))<a href="{{ route('admin.library.index') }}">Physical Library</a>@endif
            @if(auth()->user()->canAccess('digital-library.manage'))<a href="{{ route('admin.digital-resources.index') }}">Digital Library</a>@endif
            @if(auth()->user()->canAccess('jobs.manage'))<a href="{{ route('admin.jobs.index') }}">Jobs</a>@endif
            @if(auth()->user()->canAccess('communications.manage'))<a href="{{ route('admin.communications.index') }}">Communications</a>@endif

            <div class="nav-section">Management</div>
            @if(auth()->user()->canAccess('staff.manage'))<a href="{{ route('admin.staff.index') }}">Staff & Payroll</a>@endif
            @if(auth()->user()->canAccess('reports.view'))<a href="{{ route('admin.reports.index') }}">Reports</a>@endif
            @if(auth()->user()->isGlobalAdmin() && auth()->user()->canAccess('settings.manage'))<a href="{{ route('admin.settings.index') }}">Settings</a><a href="{{ route('admin.cms.index') }}">Website CMS</a>@endif
            @if(auth()->user()->isGlobalAdmin() && auth()->user()->canAccess('roles.manage'))<a href="{{ route('admin.security.index') }}">Users & Permissions</a>@endif
        </nav>
    </aside>

    <div class="main">
        <header class="topbar">
            <div class="user">Signed in as <strong>{{ auth()->user()->name }}</strong></div>
            <form method="POST" action="{{ route('logout') }}">@csrf<button class="logout" type="submit">Logout</button></form>
        </header>

        <main class="content">
            <div class="hero">
                <div><h1>Dashboard</h1><p>Today’s operational and financial snapshot.</p></div>
                <div class="quick">
                    <a href="{{ route('admission.create') }}" target="_blank" rel="noopener">Public Admission Form</a>
                    <a href="{{ route('home') }}" target="_blank" rel="noopener">Open Website</a>
                </div>
            </div>

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

            <section class="section">
                <h2>Quick Modules</h2>
                <div class="modules">
                    @if(auth()->user()->canAccess('admissions.manage'))<a class="module" href="{{ route('admin.admissions.index') }}"><strong>Admissions</strong><span>Review and approve applications</span></a>@endif
                    @if(auth()->user()->canAccess('students.manage'))<a class="module" href="{{ route('admin.students.index') }}"><strong>Students</strong><span>Profiles, memberships and renewals</span></a>@endif
                    @if(auth()->user()->canAccess('attendance.manage'))<a class="module" href="{{ route('admin.attendance.index') }}"><strong>Attendance</strong><span>Check-in, check-out and QR scanning</span></a>@endif
                    @if(auth()->user()->canAccess('payments.manage'))<a class="module" href="{{ route('admin.expenses.index') }}"><strong>Cashbook</strong><span>Income, expenses and adjustments</span></a>@endif
                    @if(auth()->user()->canAccess('library.manage'))<a class="module" href="{{ route('admin.library.index') }}"><strong>Library</strong><span>Issue, return, reserve and recover books</span></a>@endif
                    @if(auth()->user()->canAccess('reports.view'))<a class="module" href="{{ route('admin.reports.index') }}"><strong>Reports</strong><span>Operational and financial reporting</span></a>@endif
                </div>
            </section>
        </main>
    </div>
</div>
</body>
</html>
