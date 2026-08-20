<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Communications - C-Net Library</title>
    <style>
        body{font-family:Arial,sans-serif;background:#f5f7fb;margin:0;color:#1f2937}.wrap{max-width:1180px;margin:30px auto;padding:0 18px}.grid{display:grid;grid-template-columns:1fr 1.4fr;gap:18px}.card{background:#fff;border:1px solid #e5e7eb;border-radius:14px;padding:18px;box-shadow:0 6px 22px rgba(15,23,42,.05);margin-bottom:18px}.muted{color:#6b7280}.btn{display:inline-block;background:#111827;color:#fff;text-decoration:none;padding:10px 13px;border-radius:9px;border:0;cursor:pointer}.table{width:100%;border-collapse:collapse}.table th,.table td{padding:10px;border-bottom:1px solid #edf0f4;text-align:left;font-size:14px}input,select,textarea{width:100%;box-sizing:border-box;padding:10px;border:1px solid #d1d5db;border-radius:9px;margin-top:5px}.field{margin-bottom:12px}@media(max-width:850px){.grid{grid-template-columns:1fr}}
    </style>
</head>
<body>
<div class="wrap">
    <div style="display:flex;justify-content:space-between;gap:12px;align-items:center;margin-bottom:20px">
        <div><h1 style="margin:0">Communications</h1><div class="muted">Templates and delivery logs</div></div>
        <a class="btn" href="{{ route('admin.dashboard') }}">Dashboard</a>
    </div>

    @if(session('success'))<div class="card" style="background:#f0fdf4;border-color:#86efac">{{ session('success') }}</div>@endif
    @if($errors->any())<div class="card" style="background:#fef2f2;border-color:#fca5a5"><ul style="margin:0;padding-left:18px">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif

    <div class="grid">
        <div>
            <div class="card">
                <h2 style="margin-top:0">Create Template</h2>
                <form method="POST" action="{{ route('admin.communications.templates.store') }}">
                    @csrf
                    <div class="field"><label>Name<input type="text" name="name" required value="{{ old('name') }}"></label></div>
                    <div class="field"><label>Slug<input type="text" name="slug" required value="{{ old('slug') }}" placeholder="fee-due-reminder"></label></div>
                    <div class="field"><label>Channel<select name="channel" required><option value="whatsapp">WhatsApp</option><option value="sms">SMS</option><option value="email">Email</option></select></label></div>
                    <div class="field"><label>Subject<input type="text" name="subject" value="{{ old('subject') }}"></label></div>
                    <div class="field"><label>Body<textarea name="body" rows="7" required>{{ old('body') }}</textarea></label></div>
                    <label style="display:flex;gap:8px;align-items:center;margin-bottom:14px"><input style="width:auto;margin:0" type="checkbox" name="status" value="1" checked> Active</label>
                    <button class="btn" type="submit">Save Template</button>
                </form>
            </div>

            <div class="card">
                <h2 style="margin-top:0">Templates</h2>
                @forelse($templates as $template)
                    <div style="padding:11px 0;border-bottom:1px solid #edf0f4">
                        <strong>{{ $template->name }}</strong>
                        <div class="muted">{{ strtoupper($template->channel) }} · {{ $template->slug }}</div>
                    </div>
                @empty
                    <div class="muted">No templates yet.</div>
                @endforelse
            </div>
        </div>

        <div class="card">
            <h2 style="margin-top:0">Communication Log</h2>
            <form method="GET" style="display:grid;grid-template-columns:1fr 1fr auto;gap:10px;margin-bottom:14px">
                <select name="channel"><option value="">All channels</option>@foreach(['email','sms','whatsapp'] as $channel)<option value="{{ $channel }}" @selected(request('channel')===$channel)>{{ strtoupper($channel) }}</option>@endforeach</select>
                <select name="status"><option value="">All statuses</option>@foreach(['queued','sent','failed','skipped'] as $status)<option value="{{ $status }}" @selected(request('status')===$status)>{{ ucfirst($status) }}</option>@endforeach</select>
                <button class="btn" type="submit">Filter</button>
            </form>
            <div style="overflow:auto">
                <table class="table">
                    <thead><tr><th>Date</th><th>Channel</th><th>Recipient</th><th>Template</th><th>Status</th></tr></thead>
                    <tbody>
                    @forelse($logs as $log)
                        @php
                            $recipient = (string) $log->recipient;
                            if (str_contains($recipient, '@')) {
                                [$local, $domain] = array_pad(explode('@', $recipient, 2), 2, '');
                                $maskedRecipient = mb_substr($local, 0, 1).str_repeat('*', max(2, mb_strlen($local) - 1)).'@'.$domain;
                            } else {
                                $digits = preg_replace('/\D+/', '', $recipient) ?? '';
                                $maskedRecipient = mb_strlen($digits) > 4 ? str_repeat('*', max(4, mb_strlen($digits) - 4)).mb_substr($digits, -4) : '****';
                            }
                        @endphp
                        <tr>
                            <td>{{ $log->created_at?->format('d M Y H:i') }}</td>
                            <td>{{ strtoupper($log->channel) }}</td>
                            <td>{{ $maskedRecipient }}</td>
                            <td>{{ $log->template?->name ?? 'Manual' }}</td>
                            <td>{{ ucfirst($log->status) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="muted">No communication logs yet.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
            {{ $logs->links() }}
        </div>
    </div>
</div>
</body>
</html>
