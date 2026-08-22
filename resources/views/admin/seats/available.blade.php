<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex,nofollow,noarchive">
    <title>Available Seats - C-Net Library</title>
    <style>
        *{box-sizing:border-box}body{font-family:Inter,Arial,sans-serif;background:#f3f6f8;margin:0;color:#172033}.wrap{max-width:1180px;margin:28px auto;padding:0 18px}.top{display:flex;justify-content:space-between;align-items:flex-start;gap:16px;margin-bottom:18px}.top h1{margin:0;color:#102a43}.muted{color:#64748b;font-size:13px;margin-top:5px}.btn{display:inline-flex;align-items:center;justify-content:center;border:0;border-radius:9px;padding:10px 14px;background:#102a43;color:#fff;text-decoration:none;font-weight:750;cursor:pointer}.card{background:#fff;border:1px solid #dfe7ed;border-radius:14px;padding:18px;box-shadow:0 5px 18px rgba(15,23,42,.04);margin-bottom:18px}.filters{display:grid;grid-template-columns:1.2fr 1.5fr 1fr 1fr auto;gap:12px;align-items:end}.field label{display:block;font-size:12px;font-weight:750;text-transform:uppercase;letter-spacing:.04em;color:#52677a;margin-bottom:6px}.field select,.field input{width:100%;padding:10px;border:1px solid #cbd5e1;border-radius:9px;background:#fff}.summary{display:flex;align-items:center;justify-content:space-between;gap:12px;margin-bottom:12px}.count{font-size:28px;font-weight:850;color:#0f766e}.seats{display:grid;grid-template-columns:repeat(auto-fill,minmax(145px,1fr));gap:10px}.seat{border:1px solid #b7e4d8;background:#f0fdfa;border-radius:11px;padding:14px}.seat strong{display:block;color:#0f5f59;font-size:17px}.seat span{display:block;color:#64748b;font-size:12px;margin-top:4px}.empty{text-align:center;padding:35px 15px;color:#64748b;background:#f8fafc;border:1px dashed #cbd5e1;border-radius:12px}@media(max-width:900px){.filters{grid-template-columns:1fr 1fr}.filters .btn{width:100%}}@media(max-width:560px){.filters{grid-template-columns:1fr}.top{flex-direction:column}.summary{align-items:flex-start;flex-direction:column}}
    </style>
</head>
<body>
<div class="wrap">
    <div class="top">
        <div>
            <h1>Available Seats</h1>
            <div class="muted">Check seat availability by branch, study slot and membership period.</div>
        </div>
        <a class="btn" href="{{ route('admin.dashboard') }}">Dashboard</a>
    </div>

    <form class="card filters" method="GET" action="{{ route('admin.seats.available') }}">
        <div class="field">
            <label for="branch_id">Branch</label>
            <select id="branch_id" name="branch_id" required>
                <option value="">Select Branch</option>
                @foreach($branches as $branch)
                    <option value="{{ $branch->id }}" @selected((string) request('branch_id') === (string) $branch->id)>
                        {{ $branch->name }} ({{ $branch->code }})
                    </option>
                @endforeach
            </select>
        </div>
        <div class="field">
            <label for="study_slot_id">Study Slot</label>
            <select id="study_slot_id" name="study_slot_id" required>
                <option value="">Select Study Slot</option>
                @foreach($slots as $slot)
                    <option value="{{ $slot->id }}" data-branch="{{ $slot->branch_id }}" @selected((string) request('study_slot_id') === (string) $slot->id)>
                        {{ $slot->name }}
                        @if($slot->is_24x7) · 24×7
                        @elseif($slot->start_time && $slot->end_time) · {{ substr($slot->start_time,0,5) }}–{{ substr($slot->end_time,0,5) }}
                        @elseif($slot->is_flexible) · Flexible
                        @endif
                    </option>
                @endforeach
            </select>
        </div>
        <div class="field">
            <label for="allocated_from">From</label>
            <input id="allocated_from" type="date" name="allocated_from" value="{{ $from }}" required>
        </div>
        <div class="field">
            <label for="allocated_to">To</label>
            <input id="allocated_to" type="date" name="allocated_to" value="{{ $to }}" required>
        </div>
        <button class="btn" type="submit">Check Availability</button>
    </form>

    <div class="card">
        @if($seats === null)
            <div class="empty">Select a branch and study slot to view available seats.</div>
        @else
            <div class="summary">
                <div>
                    <strong>{{ $selectedBranch?->name }}</strong>
                    <div class="muted">{{ $selectedSlot?->name }} · {{ $from }} to {{ $to }}</div>
                </div>
                <div><span class="count">{{ $seats->count() }}</span> available</div>
            </div>

            @if($seats->isEmpty())
                <div class="empty">No seats are available for the selected slot and period.</div>
            @else
                <div class="seats">
                    @foreach($seats as $seat)
                        <div class="seat">
                            <strong>Seat {{ $seat['seat_no'] }}</strong>
                            <span>{{ $seat['hall'] ?: 'Study Hall' }}</span>
                        </div>
                    @endforeach
                </div>
            @endif
        @endif
    </div>
</div>
<script>
    const branch = document.getElementById('branch_id');
    const slot = document.getElementById('study_slot_id');
    const slotOptions = Array.from(slot.options);

    function filterSlots() {
        const branchId = branch.value;
        slotOptions.forEach((option, index) => {
            if (index === 0) return;
            option.hidden = !branchId || option.dataset.branch !== branchId;
            option.disabled = option.hidden;
        });
        if (slot.selectedOptions[0]?.disabled) slot.value = '';
    }

    branch.addEventListener('change', filterSlots);
    filterSlots();
</script>
</body>
</html>
