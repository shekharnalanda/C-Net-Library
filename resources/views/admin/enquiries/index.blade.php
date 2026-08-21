<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Enquiry CRM - C-Net Library</title>
    <style>
        body{font-family:Arial,sans-serif;background:#f5f7fb;margin:0;color:#1f2937}.wrap{max-width:1240px;margin:30px auto;padding:0 18px}.top{display:flex;justify-content:space-between;gap:12px;align-items:center;margin-bottom:18px}.card{background:#fff;border:1px solid #e5e7eb;border-radius:14px;padding:18px;box-shadow:0 6px 22px rgba(15,23,42,.05);margin-bottom:16px}.stats{display:grid;grid-template-columns:repeat(6,1fr);gap:10px;margin-bottom:16px}.stat{background:#fff;border:1px solid #e5e7eb;border-radius:12px;padding:14px}.stat strong{display:block;font-size:24px;margin-top:5px}.filters{display:grid;grid-template-columns:2fr 1fr 1fr 1fr auto;gap:10px}.lead{display:grid;grid-template-columns:1.4fr 1fr 1.8fr;gap:16px;align-items:start}.muted{color:#6b7280}.label{font-size:12px;text-transform:uppercase;color:#6b7280}.value{font-weight:600;margin-top:3px}.btn{display:inline-block;background:#111827;color:#fff;border:0;border-radius:9px;padding:9px 12px;text-decoration:none;cursor:pointer}.btn.alt{background:#2563eb}.btn.green{background:#047857}.btn.light{background:#fff;color:#111827;border:1px solid #d1d5db}input,select,textarea{width:100%;box-sizing:border-box;padding:9px;border:1px solid #d1d5db;border-radius:8px}.field{margin-bottom:9px}.success{background:#f0fdf4;border:1px solid #86efac;padding:12px;border-radius:10px;margin-bottom:14px}.danger{color:#b91c1c;font-weight:700}.today{color:#b45309;font-weight:700}@media(max-width:1000px){.stats{grid-template-columns:repeat(3,1fr)}.filters{grid-template-columns:1fr 1fr}}@media(max-width:850px){.lead{grid-template-columns:1fr}.top{align-items:flex-start;flex-direction:column}}@media(max-width:560px){.stats,.filters{grid-template-columns:1fr}}
    </style>
</head>
<body><div class="wrap">
    <div class="top"><div><h1 style="margin:0">Enquiry CRM</h1><div class="muted">Lead pipeline, follow-ups and admission conversion</div></div><div><a class="btn light" href="{{ route('admin.admissions.index') }}">Admissions</a> <a class="btn" href="{{ route('admin.dashboard') }}">Dashboard</a></div></div>
    @if(session('success'))<div class="success">{{ session('success') }}</div>@endif
    @if($errors->any())<div class="card" style="border-color:#fca5a5;background:#fef2f2"><ul style="margin:0;padding-left:18px">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif

    <div class="stats">
        <div class="stat"><span class="label">All Leads</span><strong>{{ number_format($summary['total']) }}</strong></div>
        <div class="stat"><span class="label">New</span><strong>{{ number_format($summary['new']) }}</strong></div>
        <div class="stat"><span class="label">Follow-up</span><strong>{{ number_format($summary['follow_up']) }}</strong></div>
        <div class="stat"><span class="label">Qualified</span><strong>{{ number_format($summary['qualified']) }}</strong></div>
        <div class="stat"><span class="label">Due Today</span><strong>{{ number_format($summary['due_today']) }}</strong></div>
        <div class="stat"><span class="label">Overdue</span><strong class="{{ $summary['overdue_follow_up'] ? 'danger' : '' }}">{{ number_format($summary['overdue_follow_up']) }}</strong></div>
    </div>

    <div class="card"><form method="GET" class="filters">
        <input name="q" value="{{ request('q') }}" placeholder="Name, mobile, email or enquiry no">
        <select name="status"><option value="">All Status</option>@foreach(['new','contacted','follow_up','qualified','converted','lost'] as $status)<option value="{{ $status }}" @selected(request('status')===$status)>{{ ucwords(str_replace('_',' ',$status)) }}</option>@endforeach</select>
        <select name="assigned_to"><option value="">All Assignees</option>@foreach($staff as $member)<option value="{{ $member->id }}" @selected((string)request('assigned_to')===(string)$member->id)>{{ $member->name }}</option>@endforeach</select>
        <select name="follow_up"><option value="">All Follow-ups</option><option value="overdue" @selected(request('follow_up')==='overdue')>Overdue</option><option value="today" @selected(request('follow_up')==='today')>Due Today</option><option value="upcoming" @selected(request('follow_up')==='upcoming')>Upcoming</option><option value="unassigned" @selected(request('follow_up')==='unassigned')>Unassigned</option></select>
        <button class="btn" type="submit">Filter</button>
    </form></div>

    @forelse($enquiries as $enquiry)
        @php($isOverdue = !in_array($enquiry->status,['converted','lost'],true) && $enquiry->follow_up_date && $enquiry->follow_up_date->isBefore(today()))
        @php($isToday = !in_array($enquiry->status,['converted','lost'],true) && $enquiry->follow_up_date && $enquiry->follow_up_date->isToday())
        <div class="card"><div class="lead">
            <div><div class="label">Lead</div><div class="value">{{ $enquiry->name }}</div><div>{{ $enquiry->mobile }}</div><div class="muted">{{ $enquiry->email ?: 'No email' }}</div><div class="muted" style="margin-top:6px">{{ $enquiry->enquiry_no }} · {{ $enquiry->branch?->name ?? 'Any Branch' }}</div>@if($enquiry->interested_plan)<div style="margin-top:8px"><strong>Interest:</strong> {{ $enquiry->interested_plan }}</div>@endif @if($enquiry->message)<div style="margin-top:8px">{{ $enquiry->message }}</div>@endif</div>
            <div><div class="label">Pipeline</div><div class="value">{{ ucwords(str_replace('_',' ',$enquiry->status)) }}</div><div class="muted">Source: {{ $enquiry->source ?: '—' }}</div><div class="muted">Assigned: {{ $enquiry->assignee?->name ?? 'Unassigned' }}</div><div class="{{ $isOverdue ? 'danger' : ($isToday ? 'today' : 'muted') }}">Follow-up: {{ $enquiry->follow_up_date?->format('d M Y') ?? '—' }} @if($isOverdue) · OVERDUE @elseif($isToday) · TODAY @endif</div>@if($enquiry->convertedAdmission)<div style="margin-top:10px"><a href="{{ route('admin.admissions.show',$enquiry->convertedAdmission) }}">Open Admission {{ $enquiry->convertedAdmission->application_no }}</a></div>@endif</div>
            <div>
                @if(!in_array($enquiry->status,['converted','lost'],true))
                <form method="POST" action="{{ route('admin.enquiries.update',$enquiry) }}">@csrf @method('PATCH')
                    <div class="field"><select name="status">@foreach(['new','contacted','follow_up','qualified','lost'] as $status)<option value="{{ $status }}" @selected($enquiry->status===$status)>{{ ucwords(str_replace('_',' ',$status)) }}</option>@endforeach</select></div>
                    <div class="field"><select name="assigned_to"><option value="">Unassigned</option>@foreach($staff as $member)<option value="{{ $member->id }}" @selected($enquiry->assigned_to==$member->id)>{{ $member->name }} ({{ str_replace('_',' ',$member->role) }})</option>@endforeach</select></div>
                    <div class="field"><input type="date" name="follow_up_date" value="{{ optional($enquiry->follow_up_date)->format('Y-m-d') }}"></div>
                    <div class="field"><textarea name="follow_up_notes" rows="3" placeholder="Call outcome, next action or lost reason">{{ $enquiry->follow_up_notes }}</textarea></div>
                    <button class="btn alt" type="submit">Save Follow-up</button>
                </form>
                <form method="POST" action="{{ route('admin.enquiries.convert',$enquiry) }}" style="margin-top:10px" onsubmit="return confirm('Convert this enquiry into an admission application?')">@csrf<button class="btn green" type="submit">Convert to Admission</button></form>
                @else
                    <div class="muted">This lead is closed and read-only.</div>
                    @if($enquiry->follow_up_notes)<div style="margin-top:8px">{{ $enquiry->follow_up_notes }}</div>@endif
                @endif
            </div>
        </div></div>
    @empty<div class="card muted">No enquiries found.</div>@endforelse
    <div>{{ $enquiries->links() }}</div>
</div></body></html>
