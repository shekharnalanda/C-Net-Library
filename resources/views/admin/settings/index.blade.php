<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>System Settings - C-Net Library</title>
    <style>
        body{font-family:Arial,sans-serif;background:#f5f7fb;margin:0;color:#1f2937}.wrap{max-width:1050px;margin:30px auto;padding:0 18px}.top{display:flex;justify-content:space-between;align-items:center;gap:12px;margin-bottom:20px}.card{background:#fff;border:1px solid #e5e7eb;border-radius:14px;padding:18px;box-shadow:0 6px 22px rgba(15,23,42,.05);margin-bottom:18px}.grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:14px}.field{margin-bottom:12px}.label{font-size:12px;text-transform:uppercase;color:#6b7280;margin-bottom:5px}.hint{font-size:12px;color:#6b7280;margin-top:4px}input,textarea{width:100%;box-sizing:border-box;padding:10px;border:1px solid #d1d5db;border-radius:9px}.btn{display:inline-block;background:#111827;color:#fff;text-decoration:none;padding:10px 14px;border-radius:9px;border:0;cursor:pointer}.muted{color:#6b7280}@media(max-width:760px){.grid{grid-template-columns:1fr}.top{align-items:flex-start;flex-direction:column}}
    </style>
</head>
<body>
<div class="wrap">
    <div class="top">
        <div>
            <h1 style="margin:0">System Settings</h1>
            <div class="muted">Institute, finance, library, attendance and code configuration.</div>
        </div>
        <a class="btn" href="{{ route('admin.dashboard') }}">Dashboard</a>
    </div>

    @if(session('success'))
        <div class="card" style="border-color:#86efac;background:#f0fdf4">{{ session('success') }}</div>
    @endif

    <form method="POST" action="{{ route('admin.settings.update') }}">
        @csrf
        @method('PATCH')

        @foreach($settings as $group => $items)
            <div class="card">
                <h2 style="margin-top:0">{{ ucwords(str_replace('_',' ', $group)) }}</h2>
                <div class="grid">
                    @foreach($items as $setting)
                        <div class="field">
                            <div class="label">{{ ucwords(str_replace('_',' ', $setting->key)) }}</div>

                            @if($setting->type === 'json')
                                @php($values = json_decode($setting->value ?? '[]', true) ?: [])
                                <textarea name="settings[{{ $setting->id }}]" rows="4">{{ implode(', ', $values) }}</textarea>
                                <div class="hint">Comma-separated values. Saved as a configurable list.</div>
                            @else
                                <input
                                    type="{{ in_array($setting->type, ['integer','decimal']) ? 'number' : 'text' }}"
                                    @if($setting->type === 'decimal') step="0.01" @endif
                                    name="settings[{{ $setting->id }}]"
                                    value="{{ old('settings.'.$setting->id, $setting->value) }}"
                                >
                            @endif

                            @if($setting->branch_id)
                                <div class="hint">Branch override</div>
                            @endif
                        </div>
                    @endforeach
                </div>
            </div>
        @endforeach

        <button class="btn" type="submit">Save Settings</button>
    </form>
</div>
</body>
</html>
