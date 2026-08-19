<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Security - C-Net Library</title>
    <style>
        body{font-family:Arial,sans-serif;background:#f5f7fb;margin:0;color:#1f2937}.wrap{max-width:1200px;margin:28px auto;padding:0 18px}.card{background:#fff;border:1px solid #e5e7eb;border-radius:14px;padding:18px;margin-bottom:18px;box-shadow:0 6px 22px rgba(15,23,42,.05)}.grid{display:grid;grid-template-columns:1fr 1fr;gap:18px}.btn{background:#111827;color:#fff;border:0;border-radius:9px;padding:9px 12px;cursor:pointer}.muted{color:#6b7280}.table{width:100%;border-collapse:collapse}.table th,.table td{padding:10px;border-bottom:1px solid #edf0f4;text-align:left;font-size:14px}.perm{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:8px;margin:12px 0}.users{display:grid;gap:12px}.row{border:1px solid #e5e7eb;border-radius:10px;padding:12px}@media(max-width:850px){.grid{grid-template-columns:1fr}.perm{grid-template-columns:1fr}}
    </style>
</head>
<body>
<div class="wrap">
    <div style="display:flex;justify-content:space-between;gap:14px;align-items:center;margin-bottom:18px">
        <div><h1 style="margin:0">Roles, Permissions & Audit</h1><div class="muted">Access control and security activity</div></div>
        <a href="{{ route('admin.dashboard') }}">Dashboard</a>
    </div>

    @if(session('success'))
        <div class="card" style="background:#f0fdf4;border-color:#86efac">{{ session('success') }}</div>
    @endif

    <div class="grid">
        <div>
            @foreach($roles as $role)
                <div class="card">
                    <h2 style="margin-top:0">{{ $role->name }}</h2>
                    <form method="POST" action="{{ route('admin.security.roles.update', $role) }}">
                        @csrf @method('PATCH')
                        <div class="perm">
                            @foreach($permissions as $permission)
                                <label>
                                    <input type="checkbox" name="permissions[]" value="{{ $permission->id }}" {{ $role->permissions->contains('id',$permission->id) ? 'checked' : '' }}>
                                    {{ $permission->name }}
                                    <span class="muted">({{ $permission->group }})</span>
                                </label>
                            @endforeach
                        </div>
                        <button class="btn" type="submit">Save Permissions</button>
                    </form>
                </div>
            @endforeach
        </div>

        <div>
            <div class="card">
                <h2 style="margin-top:0">User Roles</h2>
                <div class="users">
                    @foreach($users as $user)
                        <form class="row" method="POST" action="{{ route('admin.security.users.roles.update', $user) }}">
                            @csrf @method('PATCH')
                            <strong>{{ $user->name }}</strong><div class="muted">{{ $user->email }}</div>
                            <div style="margin:10px 0">
                                @foreach($roles as $role)
                                    <label style="display:block;margin:5px 0">
                                        <input type="checkbox" name="roles[]" value="{{ $role->id }}" {{ $user->roles->contains('id',$role->id) ? 'checked' : '' }}>
                                        {{ $role->name }}
                                    </label>
                                @endforeach
                            </div>
                            <button class="btn" type="submit">Update Roles</button>
                        </form>
                    @endforeach
                </div>
            </div>
        </div>
    </div>

    <div class="card">
        <h2 style="margin-top:0">Recent Audit Trail</h2>
        <div style="overflow:auto">
            <table class="table">
                <thead><tr><th>Time</th><th>User</th><th>Action</th><th>Entity</th><th>IP</th></tr></thead>
                <tbody>
                @forelse($logs as $log)
                    <tr>
                        <td>{{ $log->created_at?->format('d M Y H:i') }}</td>
                        <td>{{ $log->user?->name ?? 'System' }}</td>
                        <td>{{ $log->action }}</td>
                        <td>{{ $log->auditable_type ? class_basename($log->auditable_type).' #'.$log->auditable_id : '—' }}</td>
                        <td>{{ $log->ip_address ?: '—' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="muted">No audit activity yet.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
</body>
</html>
