<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SecurityBranchAssignmentTest extends TestCase
{
    use RefreshDatabase;

    public function test_branch_scoped_role_requires_branch_assignment(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $admin = $this->superAdmin();
        $user = User::factory()->create(['role' => 'accountant']);
        $role = Role::where('slug', 'accountant')->firstOrFail();

        $this->actingAs($admin)
            ->patch(route('admin.security.users.roles.update', $user), [
                'roles' => [$role->id],
                'branch_id' => null,
            ])
            ->assertSessionHasErrors('branch_id');
    }

    public function test_super_admin_cannot_be_assigned_to_branch(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $admin = $this->superAdmin();
        $target = User::factory()->create(['role' => 'super_admin']);
        $branch = Branch::factory()->create(['status' => true]);
        $role = Role::where('slug', 'super-admin')->firstOrFail();

        $this->actingAs($admin)
            ->patch(route('admin.security.users.roles.update', $target), [
                'roles' => [$role->id],
                'branch_id' => $branch->id,
            ])
            ->assertSessionHasErrors('branch_id');
    }

    public function test_student_cannot_receive_backoffice_role(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $admin = $this->superAdmin();
        $branch = Branch::factory()->create(['status' => true]);
        $studentUser = User::factory()->create(['role' => 'student']);
        $role = Role::where('slug', 'reception')->firstOrFail();

        $this->actingAs($admin)
            ->patch(route('admin.security.users.roles.update', $studentUser), [
                'roles' => [$role->id],
                'branch_id' => $branch->id,
            ])
            ->assertSessionHasErrors('roles');
    }

    public function test_valid_branch_assignment_is_persisted_and_audited(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $admin = $this->superAdmin();
        $branch = Branch::factory()->create(['status' => true]);
        $user = User::factory()->create(['role' => 'accountant', 'branch_id' => null]);
        $role = Role::where('slug', 'accountant')->firstOrFail();

        $this->actingAs($admin)
            ->patch(route('admin.security.users.roles.update', $user), [
                'roles' => [$role->id],
                'branch_id' => $branch->id,
            ])
            ->assertRedirect();

        $this->assertSame($branch->id, $user->fresh()->branch_id);
        $this->assertTrue($user->fresh()->roles->contains('id', $role->id));
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'user.roles.updated',
            'auditable_type' => User::class,
            'auditable_id' => $user->id,
        ]);
    }

    public function test_legacy_backoffice_user_gets_default_active_branch_from_seeder(): void
    {
        $branch = Branch::factory()->create(['status' => true]);
        $user = User::factory()->create(['role' => 'reception', 'branch_id' => null]);

        $this->seed(RolePermissionSeeder::class);

        $this->assertSame($branch->id, $user->fresh()->branch_id);
        $this->assertTrue($user->fresh()->roles()->where('slug', 'reception')->exists());
    }

    private function superAdmin(): User
    {
        return User::factory()->create([
            'role' => 'super_admin',
            'branch_id' => null,
            'status' => true,
        ]);
    }
}
