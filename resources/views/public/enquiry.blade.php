<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Enquiry - C-Net Library</title>
<style>
*{box-sizing:border-box}body{margin:0;font-family:Inter,Arial,sans-serif;background:#f4f7fa;color:#172033;line-height:1.55}.topbar{background:#fff;border-bottom:1px solid #e5ebf1}.nav{max-width:1040px;margin:auto;padding:16px 20px;display:flex;justify-content:space-between;align-items:center}.brand{font-size:22px;font-weight:900;color:#102a43;text-decoration:none}.nav a.link{color:#486581;text-decoration:none;font-weight:700}.wrap{max-width:1040px;margin:36px auto;padding:0 20px}.layout{display:grid;grid-template-columns:.85fr 1.15fr;gap:24px;align-items:start}.intro{background:linear-gradient(145deg,#102a43,#243b53);color:#fff;border-radius:22px;padding:30px;position:sticky;top:24px}.intro h1{font-size:34px;line-height:1.15;margin:10px 0 14px}.intro p{color:#d9e2ec}.intro a{color:#fff}.panel{background:#fff;border:1px solid #e3e9ef;border-radius:22px;padding:28px;box-shadow:0 14px 36px rgba(15,23,42,.06)}.panel h2{margin:0 0 6px;color:#102a43}.muted{color:#71879b}.grid{display:grid;grid-template-columns:1fr 1fr;gap:14px}.field{margin-bottom:14px}.field.full{grid-column:1/-1}label{display:block;font-size:13px;font-weight:800;color:#334e68;margin-bottom:6px}input,select,textarea{width:100%;padding:12px 13px;border:1px solid #ccd7e2;border-radius:10px;font-size:15px;background:#fff;color:#172033}input:focus,select:focus,textarea:focus{outline:2px solid #cbd8e4;border-color:#486581}.btn{display:inline-flex;justify-content:center;align-items:center;padding:12px 18px;border-radius:10px;border:1px solid #0f766e;background:#0f766e;color:#fff;text-decoration:none;font-weight:900;cursor:pointer}.btn.alt{background:#fff;color:#0f766e}.success,.error{padding:13px 15px;border-radius:11px;margin-bottom:16px}.success{background:#f0fdf4;border:1px solid #86efac}.error{background:#fef2f2;border:1px solid #fca5a5}.actions{display:flex;gap:10px;flex-wrap:wrap;margin-top:8px}.help{margin-top:22px;padding-top:18px;border-top:1px solid rgba(255,255,255,.2)}@media(max-width:820px){.layout{grid-template-columns:1fr}.intro{position:static}.grid{grid-template-columns:1fr}.field.full{grid-column:auto}}@media(max-width:520px){.wrap{margin:20px auto;padding:0 14px}.panel,.intro{padding:21px;border-radius:16px}.intro h1{font-size:29px}.nav{padding:14px}.nav a.link{font-size:13px}.actions .btn{width:100%}}
</style>
</head><body>
<div class="topbar"><div class="nav"><a class="brand" href="{{ route('home') }}">C-Net Library</a><a class="link" href="{{ route('home') }}">← Back to Home</a></div></div>
<div class="wrap"><div class="layout">
<aside class="intro"><div style="font-size:13px;font-weight:800;letter-spacing:1px;text-transform:uppercase">Quick Enquiry</div><h1>Not sure which plan or slot is right?</h1><p>Send your details and study preference. The team can follow up about branch, plan, timing and seat availability.</p><div class="help"><strong>Already decided?</strong><p style="margin-bottom:0">You can skip the enquiry and <a href="{{ route('admission.create') }}">apply for admission directly</a>.</p></div></aside>
<main class="panel"><h2>Tell us what you need</h2><div class="muted" style="margin-bottom:18px">A short enquiry is enough. Required fields are marked with *.</div>
@if(session('success'))<div class="success">{{ session('success') }}</div>@endif
@if($errors->any())<div class="error"><strong>Please correct the following:</strong><ul style="margin-bottom:0">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
<form method="POST" action="{{ route('enquiry.store') }}">@csrf
<div aria-hidden="true" style="position:absolute;left:-10000px;width:1px;height:1px;overflow:hidden"><label>Website<input type="text" name="website" value="" tabindex="-1" autocomplete="off"></label></div>
<div class="grid">
<div class="field"><label>Name *</label><input name="name" value="{{ old('name') }}" autocomplete="name" required></div>
<div class="field"><label>Mobile *</label><input type="tel" name="mobile" value="{{ old('mobile') }}" autocomplete="tel" inputmode="tel" required></div>
<div class="field"><label>Email</label><input type="email" name="email" value="{{ old('email') }}" autocomplete="email"></div>
<div class="field"><label>Preferred Branch</label><select name="branch_id"><option value="">Any Branch</option>@foreach($branches as $branch)<option value="{{ $branch->id }}" @selected(old('branch_id')==$branch->id)>{{ $branch->name }}</option>@endforeach</select></div>
<div class="field"><label>Interested Plan</label><input name="interested_plan" value="{{ old('interested_plan') }}" placeholder="e.g. 8 Hour / 12 Hour / 24x7"></div>
<div class="field"><label>How did you hear about us?</label><select name="source"><option value="">Select</option>@foreach(['Walk-in','Google','Facebook','Instagram','Referral','WhatsApp','Other'] as $source)<option value="{{ $source }}" @selected(old('source')===$source)>{{ $source }}</option>@endforeach</select></div>
<div class="field full"><label>Message</label><textarea name="message" rows="4" placeholder="Preferred timing, questions, or anything else the team should know">{{ old('message') }}</textarea></div>
</div>
<div class="actions"><button class="btn" type="submit">Submit Enquiry</button><a class="btn alt" href="{{ route('admission.create') }}">Apply Directly</a></div>
</form></main>
</div></div></body></html>
