<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Enquiry - C-Net Library</title>
    <style>
        body{font-family:Arial,sans-serif;background:#f5f7fb;margin:0;color:#1f2937}.wrap{max-width:760px;margin:40px auto;padding:0 18px}.card{background:#fff;border:1px solid #e5e7eb;border-radius:16px;padding:22px;box-shadow:0 8px 28px rgba(15,23,42,.06)}.grid{display:grid;grid-template-columns:1fr 1fr;gap:14px}.field{margin-bottom:14px}label{font-size:13px;font-weight:700}input,select,textarea{width:100%;box-sizing:border-box;padding:11px;border:1px solid #d1d5db;border-radius:9px;margin-top:6px}.btn{background:#111827;color:#fff;border:0;border-radius:9px;padding:11px 16px;cursor:pointer}.success{background:#f0fdf4;border:1px solid #86efac;padding:12px;border-radius:10px;margin-bottom:16px}.error{background:#fef2f2;border:1px solid #fca5a5;padding:12px;border-radius:10px;margin-bottom:16px}@media(max-width:700px){.grid{grid-template-columns:1fr}}
    </style>
</head>
<body>
<div class="wrap">
    <div class="card">
        <h1 style="margin-top:0">Library Enquiry</h1>
        <p style="color:#6b7280">Tell us what study plan or facility you are looking for. Our team can follow up with you.</p>

        @if(session('success'))<div class="success">{{ session('success') }}</div>@endif
        @if($errors->any())
            <div class="error"><ul style="margin:0;padding-left:18px">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
        @endif

        <form method="POST" action="{{ route('enquiry.store') }}">
            @csrf
            <div aria-hidden="true" style="position:absolute;left:-10000px;width:1px;height:1px;overflow:hidden">
                <label>Website<input type="text" name="website" value="" tabindex="-1" autocomplete="off"></label>
            </div>
            <div class="grid">
                <div class="field"><label>Name<input name="name" value="{{ old('name') }}" required></label></div>
                <div class="field"><label>Mobile<input name="mobile" value="{{ old('mobile') }}" required></label></div>
                <div class="field"><label>Email<input type="email" name="email" value="{{ old('email') }}"></label></div>
                <div class="field"><label>Branch<select name="branch_id"><option value="">Any Branch</option>@foreach($branches as $branch)<option value="{{ $branch->id }}" @selected(old('branch_id')==$branch->id)>{{ $branch->name }}</option>@endforeach</select></label></div>
                <div class="field"><label>Interested Plan<input name="interested_plan" value="{{ old('interested_plan') }}" placeholder="e.g. 8 Hour / 24x7"></label></div>
                <div class="field"><label>How did you hear about us?<select name="source"><option value="">Select</option>@foreach(['Walk-in','Google','Facebook','Instagram','Referral','WhatsApp','Other'] as $source)<option value="{{ $source }}" @selected(old('source')===$source)>{{ $source }}</option>@endforeach</select></label></div>
            </div>
            <div class="field"><label>Message<textarea name="message" rows="4">{{ old('message') }}</textarea></label></div>
            <button class="btn" type="submit">Submit Enquiry</button>
        </form>
    </div>
</div>
</body>
</html>
