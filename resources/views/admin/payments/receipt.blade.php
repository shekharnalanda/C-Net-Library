<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Receipt {{ $payment->receipt_no }} - C-Net Library</title>
    <style>
        body{font-family:Arial,sans-serif;background:#f3f4f6;margin:0;color:#111827}.wrap{max-width:760px;margin:32px auto;padding:0 16px}.receipt,.panel{background:#fff;border:1px solid #e5e7eb;border-radius:14px;padding:28px;box-shadow:0 8px 26px rgba(15,23,42,.06);margin-bottom:18px}.head{display:flex;justify-content:space-between;gap:20px;border-bottom:2px solid #111827;padding-bottom:18px;margin-bottom:20px}.muted{color:#6b7280}.grid{display:grid;grid-template-columns:1fr 1fr;gap:12px}.row{display:flex;justify-content:space-between;gap:16px;padding:9px 0;border-bottom:1px dashed #d1d5db}.total{font-weight:700;font-size:18px}.actions{margin-top:18px;display:flex;gap:10px;flex-wrap:wrap}.btn{display:inline-block;border:0;border-radius:9px;padding:10px 14px;background:#111827;color:#fff;text-decoration:none;cursor:pointer}.field{margin-bottom:12px}input,select,textarea{width:100%;box-sizing:border-box;padding:10px;border:1px solid #d1d5db;border-radius:9px;margin-top:5px}.history{width:100%;border-collapse:collapse}.history th,.history td{padding:9px;border-bottom:1px solid #e5e7eb;text-align:left;font-size:14px}@media print{body{background:#fff}.wrap{margin:0;max-width:none;padding:0}.receipt{box-shadow:none;border:0}.actions,.panel{display:none}}@media(max-width:600px){.head,.grid{grid-template-columns:1fr;display:grid}.head{gap:8px}}
    </style>
</head>
<body>
<div class="wrap">
    @if(session('success'))<div class="panel" style="border-color:#86efac;background:#f0fdf4">{{ session('success') }}</div>@endif
    @if($errors->any())<div class="panel" style="border-color:#fca5a5;background:#fef2f2"><ul style="margin:0;padding-left:18px">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif

    <div class="receipt">
        <div class="head">
            <div>
                <h1 style="margin:0">C-Net Library</h1>
                <div class="muted">Study Library & Digital Learning Platform</div>
                <div class="muted">{{ $payment->student?->branch?->name ?? 'Main Branch' }}</div>
            </div>
            <div style="text-align:right">
                <strong>PAYMENT RECEIPT</strong>
                <div class="muted">{{ $payment->receipt_no }}</div>
                <div class="muted">{{ $payment->payment_date?->format('d M Y') }}</div>
            </div>
        </div>

        <div class="grid" style="margin-bottom:20px">
            <div><strong>Student</strong><br>{{ $payment->student?->name }}</div>
            <div><strong>Student ID</strong><br>{{ $payment->student?->student_code }}</div>
            <div><strong>Plan</strong><br>{{ $payment->membership?->feePlan?->name ?? '—' }}</div>
            <div><strong>Study Slot</strong><br>{{ $payment->membership?->studySlot?->name ?? '—' }}</div>
        </div>

        <div class="row"><span>Total Membership Fee</span><strong>₹{{ number_format((float)$payment->membership?->final_fee, 2) }}</strong></div>
        <div class="row"><span>Previous Net Paid</span><strong>₹{{ number_format($previousPaid, 2) }}</strong></div>
        <div class="row"><span>Original Payment</span><strong>₹{{ number_format((float)$payment->amount, 2) }}</strong></div>
        @if($currentAdjusted > 0)
            <div class="row"><span>Adjustments Against This Payment</span><strong>-₹{{ number_format($currentAdjusted, 2) }}</strong></div>
        @endif
        <div class="row total"><span>Current Net Payment</span><span>₹{{ number_format($currentNet, 2) }}</span></div>
        <div class="row"><span>Balance Due</span><strong>₹{{ number_format($balanceDue, 2) }}</strong></div>
        <div class="row"><span>Payment Mode</span><strong>{{ strtoupper(str_replace('_', ' ', $payment->payment_mode)) }}</strong></div>
        @if($payment->transaction_ref)
            <div class="row"><span>Transaction Ref</span><strong>{{ $payment->transaction_ref }}</strong></div>
        @endif
        <div class="row"><span>Received By</span><strong>{{ $payment->receiver?->name ?? 'Admin' }}</strong></div>

        @if($payment->remarks)
            <p><strong>Remarks:</strong> {{ $payment->remarks }}</p>
        @endif

        @if($payment->adjustments->isNotEmpty())
            <h3>Adjustment History</h3>
            <div style="overflow:auto">
                <table class="history">
                    <thead><tr><th>Date</th><th>Type</th><th>Amount</th><th>Reason</th><th>By</th></tr></thead>
                    <tbody>
                    @foreach($payment->adjustments as $adjustment)
                        <tr>
                            <td>{{ $adjustment->adjustment_date?->format('d M Y') }}</td>
                            <td>{{ ucfirst($adjustment->type) }}</td>
                            <td>₹{{ number_format((float)$adjustment->amount, 2) }}</td>
                            <td>{{ $adjustment->reason }}</td>
                            <td>{{ $adjustment->creator?->name ?? 'Admin' }}</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        @endif

        <p class="muted" style="margin-top:24px">This is a computer-generated receipt. Original payment records are immutable; corrections are recorded as separate adjustments.</p>
    </div>

    @if($currentNet > 0)
        <div class="panel">
            <h2 style="margin-top:0">Record Adjustment</h2>
            <p class="muted">Use this for refunds, reversals or corrections. The original payment is not edited or deleted.</p>
            <form method="POST" action="{{ route('admin.payments.adjust', $payment) }}">
                @csrf
                <div class="grid">
                    <div class="field"><label>Type<select name="type" required><option value="refund">Refund</option><option value="reversal">Reversal</option><option value="correction">Correction</option></select></label></div>
                    <div class="field"><label>Amount<input type="number" name="amount" min="0.01" max="{{ $currentNet }}" step="0.01" required></label></div>
                </div>
                <div class="field"><label>Reason<textarea name="reason" rows="3" maxlength="1000" required></textarea></label></div>
                <button class="btn" type="submit">Record Adjustment</button>
            </form>
        </div>
    @endif

    <div class="actions">
        <button class="btn" onclick="window.print()">Print Receipt</button>
        <a class="btn" href="{{ route('admin.students.show', $payment->student) }}">Student Profile</a>
    </div>
</div>
</body>
</html>
