<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Services\AuditService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SecurityController extends Controller
{
    public function index(Request $request): View
    {
        $roles = Role::with('permissions')->orderBy('name')->get();
        $permissions = Permission::orderBy('group')->orderBy('name')->get();
        $users = User::with('roles')->orderBy('name')->get();
        $logs = AuditLog::with('user')
            ->latest()
            ->limit(100)
            ->get();

        return view('admin.security.index', compact('roles', 'permissions', 'users', 'logs'));
    }

    public function updateRole(Request $request, Role $role, AuditService $audit): RedirectResponse
    {
        $data = $request->validate([
            'permissions' => ['array'],
            'permissions.*' => ['integer', 'exists:permissions,id'],
        ]);

        $old = $role->permissions()->pluck('permissions.id')->all();
        $role->permissions()->sync($data['permissions'] ?? []);
        $new = $role->permissions()->pluck('permissions.id')->all();

        $audit->log('role.permissions.updated', $role, ['permissions' => $old], ['permissions' => $new]);

        return back()->with('success', 'Role permissions updated.');
    }

    public function updateUserRoles(Request $request, User $user, AuditService $audit): RedirectResponse
    {
        $data = $request->validate([
            'roles' => ['array'],
            'roles.*' => ['integer', 'exists:roles,id'],
        ]);

        $old = $user->roles()->pluck('roles.id')->all();
        $user->roles()->sync($data['roles'] ?? []);
        $new = $user->roles()->pluck('roles.id')->all();

        $audit->log('user.roles.updated', $user, ['roles' => $old], ['roles' => $new]);

        return back()->with('success', 'User roles updated.');
    }
}
