<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Services\AuditService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class SecurityController extends Controller
{
    public function index(Request $request): View
    {
        $roles = Role::with('permissions')->orderBy('name')->get();
        $permissions = Permission::orderBy('group')->orderBy('name')->get();

        $users = User::with(['roles', 'branch'])
            ->when($request->filled('user_search'), function ($query) use ($request) {
                $search = trim((string) $request->input('user_search'));
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                });
            })
            ->when($request->filled('branch_id'), fn ($query) => $query->where('branch_id', $request->integer('branch_id')))
            ->orderBy('name')
            ->get();

        $branches = Branch::query()->where('status', true)->orderBy('name')->get();

        $logs = AuditLog::with('user')
            ->when($request->filled('audit_search'), function ($query) use ($request) {
                $search = trim((string) $request->input('audit_search'));
                $query->where(function ($q) use ($search) {
                    $q->where('action', 'like', "%{$search}%")
                        ->orWhere('auditable_type', 'like', "%{$search}%")
                        ->orWhere('ip_address', 'like', "%{$search}%")
                        ->orWhereHas('user', fn ($userQuery) => $userQuery
                            ->where('name', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%"));
                });
            })
            ->latest()
            ->limit(100)
            ->get();

        $securityStats = [
            'users' => User::query()->count(),
            'backoffice_users' => User::query()->where('role', '!=', 'student')->count(),
            'global_users' => User::query()->whereNull('branch_id')->where('role', '!=', 'student')->count(),
            'roles' => $roles->count(),
            'permissions' => $permissions->count(),
            'audit_events' => AuditLog::query()->count(),
        ];

        return view('admin.security.index', compact('roles', 'permissions', 'users', 'branches', 'logs', 'securityStats'));
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
            'branch_id' => ['nullable', 'integer', 'exists:branches,id'],
        ]);

        $selectedRoles = Role::query()
            ->whereIn('id', $data['roles'] ?? [])
            ->get(['id', 'slug']);
        $selectedSlugs = $selectedRoles->pluck('slug');

        if ($user->role === 'student' && $selectedRoles->isNotEmpty()) {
            throw ValidationException::withMessages([
                'roles' => 'Student portal users cannot be assigned backoffice roles.',
            ]);
        }

        $hasGlobalRole = $selectedSlugs->contains('super-admin') || $user->role === 'super_admin';
        $hasBranchScopedRole = $selectedSlugs->intersect([
            'branch-admin', 'reception', 'accountant', 'librarian', 'counselor', 'staff',
        ])->isNotEmpty() || in_array($user->role, ['admin', 'reception', 'accountant', 'librarian'], true);

        if ($hasGlobalRole && filled($data['branch_id'] ?? null)) {
            throw ValidationException::withMessages([
                'branch_id' => 'Super-admin users must remain global and cannot be assigned to a branch.',
            ]);
        }

        if (! $hasGlobalRole && $hasBranchScopedRole && empty($data['branch_id'])) {
            throw ValidationException::withMessages([
                'branch_id' => 'A branch assignment is required for branch-scoped backoffice roles.',
            ]);
        }

        $old = [
            'roles' => $user->roles()->pluck('roles.id')->all(),
            'branch_id' => $user->branch_id,
        ];

        $user->roles()->sync($selectedRoles->pluck('id')->all());
        $user->update([
            'branch_id' => $hasGlobalRole ? null : ($data['branch_id'] ?? null),
        ]);

        $new = [
            'roles' => $user->roles()->pluck('roles.id')->all(),
            'branch_id' => $user->fresh()->branch_id,
        ];

        $audit->log('user.roles.updated', $user, $old, $new);

        return back()->with('success', 'User roles and branch assignment updated.');
    }
}
