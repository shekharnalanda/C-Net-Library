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
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class SecurityController extends Controller
{
    private const BACKOFFICE_ROLE_SLUGS = [
        'branch-admin', 'reception', 'accountant', 'librarian', 'counselor', 'staff',
    ];

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
        $backofficeRoles = Role::query()->whereIn('slug', self::BACKOFFICE_ROLE_SLUGS)->orderBy('name')->get();

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

        return view('admin.security.index', compact('roles', 'permissions', 'users', 'branches', 'backofficeRoles', 'logs', 'securityStats'));
    }

    public function createBackofficeUser(Request $request, AuditService $audit): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:190', 'unique:users,email'],
            'password' => ['required', 'confirmed', Password::min(8)->letters()->numbers()],
            'branch_id' => ['required', 'integer', 'exists:branches,id'],
            'primary_role' => ['required', Rule::in(self::BACKOFFICE_ROLE_SLUGS)],
            'status' => ['nullable', 'boolean'],
        ]);

        $role = Role::query()->where('slug', $data['primary_role'])->firstOrFail();
        $legacyRole = in_array($data['primary_role'], ['reception', 'accountant', 'librarian'], true)
            ? $data['primary_role']
            : 'admin';

        $user = User::create([
            'branch_id' => $data['branch_id'],
            'name' => $data['name'],
            'email' => strtolower($data['email']),
            'password' => $data['password'],
            'role' => $legacyRole,
            'status' => $request->boolean('status', true),
        ]);
        $user->roles()->sync([$role->id]);

        $audit->log('backoffice.user.created', $user, [], [
            'branch_id' => $user->branch_id,
            'role' => $role->slug,
            'status' => $user->status,
        ]);

        return back()->with('success', 'Backoffice user created successfully.');
    }

    public function updateBackofficeUser(Request $request, User $user, AuditService $audit): RedirectResponse
    {
        if ($user->role === 'student') {
            throw ValidationException::withMessages(['user' => 'Student portal accounts cannot be edited as backoffice users.']);
        }
        if ($user->isGlobalAdmin()) {
            throw ValidationException::withMessages(['user' => 'Global super-admin account details are protected here. Use the dedicated super-admin process.']);
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:190', Rule::unique('users', 'email')->ignore($user->id)],
            'branch_id' => ['required', 'integer', 'exists:branches,id'],
            'primary_role' => ['required', Rule::in(self::BACKOFFICE_ROLE_SLUGS)],
            'status' => ['nullable', 'boolean'],
        ]);

        $role = Role::query()->where('slug', $data['primary_role'])->firstOrFail();
        $legacyRole = in_array($data['primary_role'], ['reception', 'accountant', 'librarian'], true)
            ? $data['primary_role']
            : 'admin';

        $old = [
            'name' => $user->name,
            'email' => $user->email,
            'branch_id' => $user->branch_id,
            'role' => $user->roles()->pluck('slug')->implode(','),
            'status' => $user->status,
        ];

        $user->update([
            'name' => $data['name'],
            'email' => strtolower($data['email']),
            'branch_id' => $data['branch_id'],
            'role' => $legacyRole,
            'status' => $request->boolean('status'),
        ]);
        $user->roles()->sync([$role->id]);

        $audit->log('backoffice.user.updated', $user, $old, [
            'name' => $user->name,
            'email' => $user->email,
            'branch_id' => $user->branch_id,
            'role' => $role->slug,
            'status' => $user->status,
        ]);

        return back()->with('success', 'Backoffice user updated successfully.');
    }

    public function resetBackofficePassword(Request $request, User $user, AuditService $audit): RedirectResponse
    {
        if ($user->role === 'student') {
            throw ValidationException::withMessages(['user' => 'Use the student account workflow for student passwords.']);
        }

        $data = $request->validate([
            'password' => ['required', 'confirmed', Password::min(8)->letters()->numbers()],
        ]);

        $user->update(['password' => $data['password']]);
        $audit->log('backoffice.user.password_reset', $user);

        return back()->with('success', 'Backoffice password reset successfully.');
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

        $selectedRoles = Role::query()->whereIn('id', $data['roles'] ?? [])->get(['id', 'slug']);
        $selectedSlugs = $selectedRoles->pluck('slug');

        if ($user->role === 'student' && $selectedRoles->isNotEmpty()) {
            throw ValidationException::withMessages(['roles' => 'Student portal users cannot be assigned backoffice roles.']);
        }

        $hasGlobalRole = $selectedSlugs->contains('super-admin') || $user->role === 'super_admin';
        $hasBranchScopedRole = $selectedSlugs->intersect(self::BACKOFFICE_ROLE_SLUGS)->isNotEmpty()
            || in_array($user->role, ['admin', 'reception', 'accountant', 'librarian'], true);

        if ($hasGlobalRole && filled($data['branch_id'] ?? null)) {
            throw ValidationException::withMessages(['branch_id' => 'Super-admin users must remain global and cannot be assigned to a branch.']);
        }
        if (! $hasGlobalRole && $hasBranchScopedRole && empty($data['branch_id'])) {
            throw ValidationException::withMessages(['branch_id' => 'A branch assignment is required for branch-scoped backoffice roles.']);
        }

        $old = ['roles' => $user->roles()->pluck('roles.id')->all(), 'branch_id' => $user->branch_id];
        $user->roles()->sync($selectedRoles->pluck('id')->all());
        $user->update(['branch_id' => $hasGlobalRole ? null : ($data['branch_id'] ?? null)]);
        $new = ['roles' => $user->roles()->pluck('roles.id')->all(), 'branch_id' => $user->fresh()->branch_id];
        $audit->log('user.roles.updated', $user, $old, $new);

        return back()->with('success', 'User roles and branch assignment updated.');
    }
}
