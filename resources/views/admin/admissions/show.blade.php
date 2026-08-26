<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Review Admission - C-Net Library</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<div class="container py-5">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-1">Review Admission</h1>
            <p class="text-muted mb-0">{{ $admission->application_no }}</p>
        </div>
        <div class="d-flex gap-2">
            <a href="{{ route('admin.study-space.index') }}" class="btn btn-outline-primary">Study Hall & Seats</a>
            <a href="{{ route('admin.lockers.index') }}" class="btn btn-outline-success">Lockers</a>
            <a href="{{ route('admin.admissions.index') }}" class="btn btn-outline-secondary">Back to Admissions</a>
        </div>
    </div>

    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    @if($errors->any())
        <div class="alert alert-danger">
            <ul class="mb-0">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="row g-4">
        <div class="col-lg-5">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <h2 class="h5 mb-3">Applicant Details</h2>
                    <dl class="row mb-0">
                        <dt class="col-5">Name</dt><dd class="col-7">{{ $admission->name }}</dd>
                        <dt class="col-5">Father/Guardian</dt><dd class="col-7">{{ $admission->father_name ?: '—' }}</dd>
                        <dt class="col-5">Mobile</dt><dd class="col-7">{{ $admission->mobile }}</dd>
                        <dt class="col-5">Email</dt><dd class="col-7">{{ $admission->email ?: '—' }}</dd>
                        <dt class="col-5">Branch</dt><dd class="col-7">{{ $admission->branch?->name ?? '—' }}</dd>
                        <dt class="col-5">Preferred Slot</dt><dd class="col-7">{{ $admission->studySlot?->name ?? '—' }}</dd>
                        <dt class="col-5">Preferred Plan</dt><dd class="col-7">{{ $admission->feePlan?->name ?? '—' }}</dd>
                        <dt class="col-5">Locker Requested</dt><dd class="col-7"><span class="badge {{ $admission->wants_locker ? 'text-bg-success' : 'text-bg-secondary' }}">{{ $admission->wants_locker ? 'YES — chargeable monthly locker required' : 'No' }}</span></dd>
                        <dt class="col-5">Status</dt><dd class="col-7">{{ str_replace('_', ' ', ucfirst($admission->status)) }}</dd>
                    </dl>
                    @if($admission->wants_locker)
                        <div class="alert alert-success mt-3 mb-0 small">This applicant requested a locker. After the student record is created, open <strong>Locker Management</strong> to allocate an available locker. The allocation automatically captures the admin-configured monthly locker charge.</div>
                    @endif
                </div>
            </div>
        </div>

        <div class="col-lg-7">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <h2 class="h5 mb-2">Approve Membership & Allocate Seat</h2>
                    <p class="text-muted small">Select the study slot and matching fee plan. Available seats are checked for the full membership validity period before approval.</p>

                    @if($admission->status === 'converted')
                        <div class="alert alert-info">This application has already been converted into a student record with membership and seat allocation.</div>
                        @if($admission->wants_locker)
                            <a class="btn btn-success" href="{{ route('admin.lockers.index') }}">Allocate Requested Locker</a>
                        @endif
                    @else
                        <form method="POST" action="{{ route('admin.admissions.approve', $admission) }}" id="approval-form">
                            @csrf

                            <div class="mb-3">
                                <label class="form-label">Study Slot</label>
                                <select name="study_slot_id" id="study_slot_id" class="form-select" required>
                                    <option value="">Select Slot</option>
                                    @foreach($studySlots as $slot)
                                        <option value="{{ $slot->id }}" @selected(old('study_slot_id', $admission->study_slot_id) == $slot->id)>
                                            {{ $slot->name }}
                                            @if($slot->is_24x7) · 24×7
                                            @elseif($slot->duration_hours) · {{ $slot->duration_hours }} hr
                                            @endif
                                        </option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Fee Plan</label>
                                <select name="fee_plan_id" id="fee_plan_id" class="form-select" required>
                                    <option value="">Select Fee Plan</option>
                                    @foreach($feePlans as $plan)
                                        <option value="{{ $plan->id }}"
                                                data-slot="{{ $plan->study_slot_id }}"
                                                data-validity="{{ $plan->validity_days }}"
                                                @selected(old('fee_plan_id', $admission->fee_plan_id) == $plan->id)>
                                            {{ $plan->name }} - ₹{{ number_format($plan->monthly_fee, 2) }} · {{ $plan->validity_days }} days
                                        </option>
                                    @endforeach
                                </select>
                                <div class="form-text">Only plans linked to the selected study slot are shown.</div>
                            </div>

                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label">Membership Start</label>
                                    <input type="date" name="start_date" id="start_date" class="form-control" value="{{ old('start_date', now()->toDateString()) }}" required>
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label">Available Seat</label>
                                    <select name="seat_id" id="seat_id" class="form-select" required>
                                        <option value="">Select slot and plan first</option>
                                    </select>
                                </div>
                            </div>

                            <div id="period_preview" class="alert alert-light border mt-3 mb-0 small">Select a fee plan to calculate the allocation period.</div>

                            @if($admission->wants_locker)
                                <div class="alert alert-success border mt-3 mb-0 small"><strong>Locker required:</strong> approve the admission first, then use Locker Management to allocate an available locker at the configured monthly charge.</div>
                            @endif

                            <div class="row g-3 mt-1">
                                <div class="col-md-6">
                                    <label class="form-label">Discount</label>
                                    <input type="number" step="0.01" min="0" name="discount" class="form-control" value="{{ old('discount', 0) }}">
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label">Remarks</label>
                                    <input type="text" name="remarks" class="form-control" value="{{ old('remarks') }}">
                                </div>
                            </div>

                            <button type="submit" class="btn btn-success mt-4">Approve + Create Membership + Allocate Seat</button>
                        </form>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>

@if($admission->status !== 'converted')
<script>
const slotSelect = document.getElementById('study_slot_id');
const planSelect = document.getElementById('fee_plan_id');
const seatSelect = document.getElementById('seat_id');
const startDate = document.getElementById('start_date');
const periodPreview = document.getElementById('period_preview');
const allPlans = Array.from(planSelect.options);

function addDays(dateText, days) {
    const d = new Date(`${dateText}T00:00:00`);
    d.setDate(d.getDate() + Math.max(1, Number(days)) - 1);
    return d.toISOString().slice(0, 10);
}

function filterPlans() {
    const slotId = slotSelect.value;
    allPlans.forEach((option, index) => {
        if (index === 0) return;
        const visible = !!slotId && option.dataset.slot === slotId;
        option.hidden = !visible;
        option.disabled = !visible;
    });

    if (planSelect.selectedOptions[0]?.disabled) planSelect.value = '';
    updatePeriod();
}

function updatePeriod() {
    const plan = planSelect.selectedOptions[0];
    const validity = Number(plan?.dataset.validity || 0);
    const from = startDate.value;

    if (!from || !validity) {
        periodPreview.textContent = 'Select a fee plan to calculate the allocation period.';
        return null;
    }

    const to = addDays(from, validity);
    periodPreview.textContent = `Membership / seat allocation period: ${from} to ${to} (${validity} days)`;
    return to;
}

async function loadSeats() {
    const slotId = slotSelect.value;
    const plan = planSelect.selectedOptions[0];
    const validity = Number(plan?.dataset.validity || 0);
    const from = startDate.value;

    if (!slotId || !planSelect.value || !from || !validity) {
        seatSelect.innerHTML = '<option value="">Select slot and plan first</option>';
        updatePeriod();
        return;
    }

    const to = updatePeriod();
    seatSelect.innerHTML = '<option value="">Loading available seats...</option>';

    const params = new URLSearchParams({
        branch_id: '{{ $admission->branch_id }}',
        study_slot_id: slotId,
        allocated_from: from,
        allocated_to: to
    });

    try {
        const response = await fetch(`{{ route('admin.seats.available') }}?${params.toString()}`, {
            headers: { 'Accept': 'application/json' }
        });
        if (!response.ok) throw new Error('Unable to load seats');

        const seats = await response.json();
        seatSelect.innerHTML = '<option value="">Select Available Seat</option>';

        seats.forEach(seat => {
            const option = document.createElement('option');
            option.value = seat.id;
            option.textContent = `${seat.hall ?? 'Study Hall'} · Seat ${seat.seat_no}`;
            seatSelect.appendChild(option);
        });

        if (seats.length === 0) {
            seatSelect.innerHTML = '<option value="">No seat available for this slot / period</option>';
        }
    } catch (error) {
        seatSelect.innerHTML = '<option value="">Could not load seats</option>';
    }
}

slotSelect.addEventListener('change', () => { filterPlans(); loadSeats(); });
planSelect.addEventListener('change', loadSeats);
startDate.addEventListener('change', loadSeats);

filterPlans();
if (slotSelect.value && planSelect.value) loadSeats();
</script>
@endif
</body>
</html>
