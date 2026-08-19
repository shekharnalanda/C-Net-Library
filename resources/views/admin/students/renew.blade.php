<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Renew Membership - C-Net Library</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 0; background: #f4f6f8; color: #1f2937; }
        .wrap { max-width: 920px; margin: 32px auto; padding: 0 16px; }
        .card { background: white; padding: 24px; border-radius: 12px; box-shadow: 0 4px 16px rgba(0,0,0,.06); margin-bottom: 18px; }
        .grid { display: grid; grid-template-columns: repeat(2, minmax(0,1fr)); gap: 16px; }
        label { display: block; font-weight: 700; margin-bottom: 6px; }
        input, select, textarea { width: 100%; box-sizing: border-box; padding: 10px; border: 1px solid #cbd5e1; border-radius: 7px; }
        .btn { display: inline-block; padding: 10px 16px; border-radius: 7px; text-decoration: none; border: 0; cursor: pointer; background: #111827; color: white; }
        .muted { color: #64748b; }
        .error { background: #fee2e2; color: #991b1b; padding: 12px; border-radius: 8px; margin-bottom: 16px; }
        @media (max-width: 700px) { .grid { grid-template-columns: 1fr; } }
    </style>
</head>
<body>
<div class="wrap">
    <p><a href="{{ route('admin.students.show', $student) }}">← Back to Student</a></p>

    <div class="card">
        <h1>Renew Membership / Change Seat</h1>
        <p><strong>{{ $student->name }}</strong> · {{ $student->student_code }}</p>
        <p class="muted">
            Current validity:
            {{ optional($student->activeMembership?->expiry_date)->format('d M Y') ?? 'No active membership' }}
        </p>
    </div>

    @if ($errors->any())
        <div class="error">
            <ul>
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form method="POST" action="{{ route('admin.students.renew.store', $student) }}" class="card">
        @csrf
        <div class="grid">
            <div>
                <label for="fee_plan_id">Fee Plan</label>
                <select name="fee_plan_id" id="fee_plan_id" required>
                    <option value="">Select Fee Plan</option>
                    @foreach ($feePlans as $plan)
                        <option value="{{ $plan->id }}" @selected(old('fee_plan_id', $student->activeMembership?->fee_plan_id) == $plan->id)>
                            {{ $plan->name }} - ₹{{ number_format((float) $plan->monthly_fee, 2) }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div>
                <label for="study_slot_id">Study Slot</label>
                <select name="study_slot_id" id="study_slot_id" required>
                    <option value="">Select Slot</option>
                    @foreach ($studySlots as $slot)
                        <option value="{{ $slot->id }}" @selected(old('study_slot_id', $student->activeMembership?->study_slot_id) == $slot->id)>
                            {{ $slot->name }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div>
                <label for="seat_id">Seat</label>
                <select name="seat_id" id="seat_id">
                    <option value="">No seat / assign later</option>
                    @foreach ($seats as $seat)
                        <option value="{{ $seat->id }}">
                            {{ $seat->studyHall?->name }} - {{ $seat->seat_no }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div>
                <label for="start_date">Requested Start Date</label>
                <input type="date" name="start_date" id="start_date" value="{{ old('start_date') }}">
                <small class="muted">If current membership is still valid, renewal starts after current expiry.</small>
            </div>

            <div>
                <label for="discount">Discount</label>
                <input type="number" step="0.01" min="0" name="discount" id="discount" value="{{ old('discount', 0) }}">
            </div>
        </div>

        <div style="margin-top:16px;">
            <label for="remarks">Remarks</label>
            <textarea name="remarks" id="remarks" rows="3">{{ old('remarks') }}</textarea>
        </div>

        <div style="margin-top:18px;">
            <button type="submit" class="btn">Renew Membership</button>
        </div>
    </form>
</div>
</body>
</html>
