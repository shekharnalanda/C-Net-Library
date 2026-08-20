<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Online Admission - C-Net Library</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-lg-8">
            <div class="card shadow-sm border-0">
                <div class="card-body p-4 p-md-5">
                    <h1 class="h3 mb-1">C-Net Library Online Admission</h1>
                    <p class="text-muted mb-4">Apply for a study seat and preferred study slot.</p>

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

                    <form method="POST" action="{{ route('admission.store') }}">
                        @csrf

                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Student Name</label>
                                <input type="text" name="name" value="{{ old('name') }}" class="form-control" required>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label">Father / Guardian Name</label>
                                <input type="text" name="father_name" value="{{ old('father_name') }}" class="form-control">
                            </div>

                            <div class="col-md-6">
                                <label class="form-label">Mobile</label>
                                <input type="text" name="mobile" value="{{ old('mobile') }}" class="form-control" required>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label">Email</label>
                                <input type="email" name="email" value="{{ old('email') }}" class="form-control">
                            </div>

                            <div class="col-md-6">
                                <label class="form-label">Branch</label>
                                <select name="branch_id" class="form-select" required>
                                    <option value="">Select Branch</option>
                                    @foreach($branches as $branch)
                                        <option value="{{ $branch->id }}" @selected(old('branch_id') == $branch->id)>
                                            {{ $branch->name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label">Preferred Study Slot</label>
                                <select name="study_slot_id" class="form-select">
                                    <option value="">Select Slot</option>
                                    @foreach($studySlots as $slot)
                                        <option value="{{ $slot->id }}" @selected(old('study_slot_id') == $slot->id)>
                                            {{ $slot->name }}{{ $slot->branch?->name ? ' — '.$slot->branch->name : '' }}
                                        </option>
                                    @endforeach
                                </select>
                                <div class="form-text">Choose a slot from the same branch selected above.</div>
                            </div>

                            <div class="col-md-12">
                                <label class="form-label">Preferred Fee Plan</label>
                                <select name="fee_plan_id" class="form-select">
                                    <option value="">Select Fee Plan</option>
                                    @foreach($feePlans as $plan)
                                        <option value="{{ $plan->id }}" @selected(old('fee_plan_id') == $plan->id)>
                                            {{ $plan->name }} - ₹{{ number_format($plan->monthly_fee, 2) }}{{ $plan->branch?->name ? ' — '.$plan->branch->name : '' }}
                                        </option>
                                    @endforeach
                                </select>
                                <div class="form-text">Only active plans for the selected branch can be submitted.</div>
                            </div>

                            <div class="col-12">
                                <label class="form-label">Address</label>
                                <textarea name="address" rows="3" class="form-control">{{ old('address') }}</textarea>
                            </div>

                            <div class="col-12">
                                <button type="submit" class="btn btn-primary px-4">Submit Application</button>
                                <a href="{{ route('home') }}" class="btn btn-outline-secondary ms-2">Back to Home</a>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
</body>
</html>
