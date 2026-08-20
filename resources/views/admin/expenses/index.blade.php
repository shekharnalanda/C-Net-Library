<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cashbook - C-Net Library</title>
    <style>
        body{font-family:Arial,sans-serif;background:#f5f7fb;margin:0;color:#1f2937}.wrap{max-width:1180px;margin:30px auto;padding:0 18px}.top{display:flex;justify-content:space-between;gap:14px;align-items:center;margin-bottom:18px}.grid{display:grid;grid-template-columns:1fr 2fr;gap:18px}.card{background:#fff;border:1px solid #e5e7eb;border-radius:14px;padding:18px;box-shadow:0 6px 22px rgba(15,23,42,.05)}.field{margin-bottom:12px}input,select,textarea{width:100%;box-sizing:border-box;padding:10px;border:1px solid #d1d5db;border-radius:9px;margin-top:5px}.btn{display:inline-block;background:#111827;color:#fff;text-decoration:none;padding:10px 13px;border-radius:9px;border:0;cursor:pointer}.btn.alt{background:#fff;color:#111827;border:1px solid #d1d5db}.table{width:100%;border-collapse:collapse}.table th,.table td{padding:10px;border-bottom:1px solid #edf0f4;text-align:left;font-size:14px;vertical-align:top}.muted{color:#6b7280}.metric{font-size:28px;font-weight:700}.filters{display:flex;gap:8px;align-items:end;flex-wrap:wrap;margin-bottom:14px}.categories{display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:10px;margin-top:12px}.category{background:#f8fafc;border-radius:10px;padding:12px}.adjust{margin-top:8px;padding-top:8px;border-top:1px dashed #d1d5db}.adjust form{display:grid;grid-template-columns:110px 110px 1fr auto;gap:6px;align-items:end}@media(max-width:850px){.grid{grid-template-columns:1fr}.top{align-items:flex-start;flex-direction:column}.adjust form{grid-template-columns:1fr}}
    </style>
</head>
<body>
<div class="wrap">
    <div class="top">
        <div><h1 style="margin:0">Cashbook & Expenses</h1><div class="muted">Append-only operating expense ledger with adjustment trail.</div></div>
        <div><a class="btn" href="{{ route('admin.reports.index') }}">Reports</a> <a class="btn" href="{{ route('admin.dashboard') }}">Dashboard</a></div>
    </div>

    @if(session('success'))<div class="card" style="margin-bottom:18px;border-color:#86efac;background:#f0fdf4">{{ session('success') }}</div>@endif
    @if($errors->any())<div class="card" style="margin-bottom:18px;border-color:#fca5a5;background:#fef2f2"><ul style="margin:0;padding-left:18px">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif

    <div class="grid">
        <div class="card">
            <h2 style="margin-top:0">Record Expense</h2>
            <form method="POST" action="{{ route('admin.expenses.store') }}">
                @csrf
                <div class="field"><label>Date<input type="date" name="expense_date" value="{{ old('expense_date', today()->toDateString()) }}" required></label></div>
                <div class="field"><label>Branch<select name="branch_id"><option value="">Global / Main</option>@foreach($branches as $branch)<option value="{{ $branch->id }}" @selected(old('branch_id') == $branch->id)>{{ $branch->name }}</option>@endforeach</select></label></div>
                <div class="field"><label>Category<input name="category" value="{{ old('category') }}" placeholder="Rent, Electricity, Internet..." required></label></div>
                <div class="field"><label>Payee<input name="payee" value="{{ old('payee') }}"></label></div>
                <div class="field"><label>Amount<input type="number" name="amount" min="0.01" step="0.01" value="{{ old('amount') }}" required></label></div>
                <div class="field"><label>Payment Mode<select name="payment_mode" required>@foreach(['cash','upi','card','bank_transfer','other'] as $mode)<option value="{{ $mode }}" @selected(old('payment_mode','cash') === $mode)>{{ strtoupper(str_replace('_',' ',$mode)) }}</option>@endforeach</select></label></div>
                <div class="field"><label>Transaction Ref<input name="transaction_ref" value="{{ old('transaction_ref') }}"></label></div>
                <div class="field"><label>Description<textarea name="description" rows="3">{{ old('description') }}</textarea></label></div>
                <button class="btn" type="submit">Record Expense</button>
            </form>
        </div>

        <div>
            <div class="card" style="margin-bottom:18px">
                <div class="muted">Net expenses in selected period</div><div class="metric">₹{{ number_format($totalExpenses,2) }}</div>
                <div class="muted">Gross ₹{{ number_format($grossExpenses,2) }} · Adjustments ₹{{ number_format($expenseAdjustments,2) }}</div>
                <div class="categories">
                    @forelse($categoryTotals as $row)
                        <div class="category"><div class="muted">{{ $row->category }} (gross)</div><strong>₹{{ number_format((float)$row->total,2) }}</strong></div>
                    @empty
                        <div class="muted">No category totals yet.</div>
                    @endforelse
                </div>
            </div>
            <div class="card">
                <form method="GET" class="filters">
                    <label>From<input type="date" name="from" value="{{ $from->toDateString() }}"></label>
                    <label>To<input type="date" name="to" value="{{ $to->toDateString() }}"></label>
                    <label>Branch<select name="branch_id"><option value="">All Branches</option>@foreach($branches as $branch)<option value="{{ $branch->id }}" @selected($branchId == $branch->id)>{{ $branch->name }}</option>@endforeach</select></label>
                    <button class="btn" type="submit">Filter</button>
                </form>
                <div style="overflow:auto">
                    <table class="table">
                        <thead><tr><th>Date</th><th>Branch</th><th>Category</th><th>Payee</th><th>Mode</th><th>Gross / Net</th><th>Recorded By</th></tr></thead>
                        <tbody>
                        @forelse($expenses as $expense)
                            @php($adjusted = (float) $expense->adjustments->sum('amount'))
                            @php($net = max(0, (float) $expense->amount - $adjusted))
                            <tr>
                                <td>{{ $expense->expense_date?->format('d M Y') }}</td>
                                <td>{{ $expense->branch?->name ?? 'Global' }}</td>
                                <td><strong>{{ $expense->category }}</strong>@if($expense->payroll)<div class="muted">Auto-posted payroll {{ sprintf('%02d/%d', $expense->payroll->month, $expense->payroll->year) }}</div>@endif @if($expense->description)<div class="muted">{{ $expense->description }}</div>@endif</td>
                                <td>{{ $expense->payee ?: '—' }}</td>
                                <td>{{ strtoupper(str_replace('_',' ',$expense->payment_mode)) }}</td>
                                <td>
                                    ₹{{ number_format((float)$expense->amount,2) }}
                                    @if($adjusted > 0)<div class="muted">Adjusted -₹{{ number_format($adjusted,2) }} · Net ₹{{ number_format($net,2) }}</div>@endif
                                    @if($net > 0)
                                        <div class="adjust">
                                            <form method="POST" action="{{ route('admin.expenses.adjustments.store', $expense) }}">
                                                @csrf
                                                <label>Type<select name="type"><option value="reversal">Reversal</option><option value="correction">Correction</option><option value="refund">Refund</option></select></label>
                                                <label>Amount<input type="number" name="amount" min="0.01" max="{{ $net }}" step="0.01" required></label>
                                                <label>Reason<input type="text" name="reason" maxlength="1000" required></label>
                                                <button class="btn alt" type="submit">Adjust</button>
                                            </form>
                                        </div>
                                    @endif
                                </td>
                                <td>{{ $expense->creator?->name ?? '—' }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="muted">No expenses recorded for this period.</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
                <div style="margin-top:14px">{{ $expenses->links() }}</div>
            </div>
        </div>
    </div>
</div>
</body>
</html>
