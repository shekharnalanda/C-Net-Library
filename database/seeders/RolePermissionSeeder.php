<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;

class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = [
            'dashboard.view' => 'Dashboard',
            'admissions.manage' => 'Admissions',
            'enquiries.manage' => 'CRM',
            'students.manage' => 'Students',
            'payments.manage' => 'Payments',
            'attendance.manage' => 'Attendance',
            'library.manage' => 'Library',
            'digital-library.manage' => 'Digital Library',
            'jobs.manage' => 'Jobs',
            'communications.manage' => 'Communications',
            'reports.view' => 'Reports',
            'staff.manage' => 'Staff',
            'settings.manage' => 'Settings',
            'roles.manage' => 'Security',
            'audit.view' => 'Security',
        ];

        foreach ($permissions as $slug => $group) {
            Permission::updateOrCreate(
                ['slug' => $slug],
                ['name' => ucwords(str_replace(['.', '-'], ' ', $slug)), 'group' => $group]
            );
        }

        $roles = [
            'super-admin' => array_keys($permissions),
            'branch-admin' => array_values(array_filter(array_keys($permissions), fn ($p) => ! in_array($p, ['roles.manage'], true))),
            'reception' => ['dashboard.view', 'admissions.manage', 'enquiries.manage', 'students.manage', 'attendance.manage'],
            'accountant' => ['dashboard.view', 'students.manage', 'payments.manage', 'reports.view'],
            'librarian' => ['dashboard.view', 'students.manage', 'library.manage', 'digital-library.manage'],
            'counselor' => ['dashboard.view', 'enquiries.manage', 'admissions.manage', 'communications.manage'],
            'staff' => ['dashboard.view', 'attendance.manage'],
        ];

        foreach ($roles as $slug => $permissionSlugs) {
            $role = Role::updateOrCreate(
                ['slug' => $slug],
                ['name' => ucwords(str_replace('-', ' ', $slug)), 'is_system' => true]
            );

            $role->permissions()->sync(
                Permission::whereIn('slug', $permissionSlugs)->pluck('id')->all()
            );
        }

        $superAdminRole = Role::where('slug', 'super-admin')->first();
        $branchAdminRole = Role::where('slug', 'branch-admin')->first();

        if ($superAdminRole) {
            User::where('role', 'super_admin')->each(
                fn (User $user) => $user->roles()->syncWithoutDetaching([$superAdminRole->id])
            );
        }

        if ($branchAdminRole) {
            User::where('role', 'admin')->each(
                fn (User $user) => $user->roles()->syncWithoutDetaching([$branchAdminRole->id])
            );
        }

        foreach (['reception', 'accountant', 'librarian'] as $legacyRole) {
            $pivotRole = Role::where('slug', $legacyRole)->first();
            if (! $pivotRole) {
                continue;
            }

            User::where('role', $legacyRole)->each(
                fn (User $user) => $user->roles()->syncWithoutDetaching([$pivotRole->id])
            );
        }
    }
}
