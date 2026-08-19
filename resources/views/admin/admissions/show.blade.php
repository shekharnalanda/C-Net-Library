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
        <a href="{{ route('admin.admissions.index') }}" class="btn btn-outline-secondary">Back to Admissions</a>
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
                        <dt class="col-5">Status</dt><dd class="col-7">{{ str_replace('_', ' ', ucfirst($admission->status)) }}</dd>
                    </dl>
                </div>
            </div>
        </div>

        <div class="col-lg-7">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <h2 class="h5 mb-3">Approve & Allocate Seat</h2>

                    @if($admission->status === 'converted')
                        <div class="alert alert-info mb-0">This application has already been converted into a student record.</div>
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
                                        </option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Fee Plan</label>
                                <select name="fee_plan_id" class="form-select" required>
                                    <option value="">Select Fee Plan</option>
                                    @foreach($feePlans as $plan)
                                        <option value="{{ $plan->id }}" @selected(old('fee_plan_id', $admission->fee_plan_id) == $plan->id)>
                                            {{ $plan->name }} - ₹{{ number_format($plan->monthly_fee, 2) }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label">Membership Start</label>
                                    <input type="date" name="start_date" id="start_date" class="form-control" value="{{ old('start_date', now()->toDateString()) }}" required>
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label">Seat</label>
                                    <select name="seat_id" id="seat_id" class="form-select" required>
                                        <option value="">Select slot first</option>
                                    </select>
                                </div>
                            </div>

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

                            <button type="submit" class="btn btn-success mt-4">Approve Admission</button>
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
const seatSelect = document.getElementById('seat_id');
const startDate = document.getElementById('start_date');

async function loadSeats() {
    const slotId = slotSelect.value;
    const date = startDate.value;

    if (!slotId || !date) {
        seatSelect.innerHTML = '<option value="">Select slot first</option>';
        return;
    }

    seatSelect.innerHTML = '<option value="">Loading available seats...</option>';

    const params = new URLSearchParams({
        branch_id: '{{ $admission->branch_id }}',
        study_slot_id: slotId,
        allocated_from: date
    });

    try {
        const response = await fetch(`{{ route('admin.seats.available') }}?${params.toString()}`);
        if (!response.ok) throw new Error('Unable to load seats');

        const seats = await response.json();
        seatSelect.innerHTML = '<option value="">Select Available Seat</option>';

        seats.forEach(seat => {
            const option = document.createElement('option');
            option.value = seat.id;
            option.textContent = `${seat.hall ?? 'Hall'} - ${seat.seat_no}`;
            seatSelect.appendChild(option);
        });

        if (seats.length === 0) {
            seatSelect.innerHTML = '<option value="">No seat available for this slot</option>';
        }
    } catch (error) {
        seatSelect.innerHTML = '<option value="">Could not load seats</option>';
    }
}

slotSelect.addEventListener('change', loadSeats);
startDate.addEventListener('change', loadSeats);

if (slotSelect.value) loadSeats();
</script>
@endif
</body>
</html>
