<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\CommunicationLog;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Student;
use App\Models\StudyHall;
use App\Models\StudySlot;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BranchIsolationEdgeCasesTest extends TestCase
{
    use RefreshDatabase;

    private function branchAdmin(Branch $branch, array $permissions): User
    {
        $user = User::factory()->create([
            'role' => 'admin',
            'branch_id' => $branch->id,
            'status' => true,
        ]);

        $role = Role::create([
            'name' => 'Scoped Admin '.uniqid(),
            'slug' => 'scoped-admin-'.uniqid(),
            'is_system' => false,
        ]);

        foreach ($permissions as $slug) {
            $permission = Permission::firstOrCreate(
                ['slug' => $slug],
                ['name' => $slug, 'group' => 'Test']
            );
            $role->permissions()->syncWithoutDetaching([$permission->id]);
        }

        $user->roles()->attach($role->id);

        return $user;
    }

    public function test_qr_preview_does_not_reveal_student_from_another_branch(): void
    {
        $branchA = Branch::factory()->create();
        $branchB = Branch::factory()->create();
        $admin = $this->branchAdmin($branchA, ['attendance.manage']);
        $student = Student::factory()->create([
            'branch_id' => $branchB->id,
            'qr_token' => 'other-branch-token',
        ]);

        $response = $this->actingAs($admin)->get(route('admin.attendance.scan', [
            'token' => $student->qr_token,
        ]));

        $response->assertOk();
        $response->assertDontSee($student->name);
        $response->assertDontSee($student->student_code);
    }

    public function test_seat_availability_rejects_slot_from_another_branch(): void
    {
        $branchA = Branch::factory()->create();
        $branchB = Branch::factory()->create();
        $admin = $this->branchAdmin($branchA, ['students.manage']);
        StudyHall::factory()->create(['branch_id' => $branchA->id]);
        $slot = StudySlot::factory()->create(['branch_id' => $branchB->id]);

        $response = $this->actingAs($admin)->getJson(route('admin.seats.available', [
            'branch_id' => $branchA->id,
            'study_slot_id' => $slot->id,
        ]));

        $response->assertStatus(422);
    }

    public function test_communication_logs_are_scoped_to_assigned_branch(): void
    {
        $branchA = Branch::factory()->create();
        $branchB = Branch::factory()->create();
        $admin = $this->branchAdmin($branchA, ['communications.manage']);

        CommunicationLog::factory()->create([
            'branch_id' => $branchA->id,
            'recipient' => 'branch-a@example.com',
        ]);
        CommunicationLog::factory()->create([
            'branch_id' => $branchB->id,
            'recipient' => 'branch-b@example.com',
        ]);

        $response = $this->actingAs($admin)->get(route('admin.communications.index'));

        $response->assertOk();
        $response->assertSee('branch-a@example.com');
        $response->assertDontSee('branch-b@example.com');
    }

    public function test_branch_admin_cannot_access_global_settings_cms_or_security_even_with_permissions(): void
    {
        $branch = Branch::factory()->create();
        $admin = $this->branchAdmin($branch, [
            'settings.manage',
            'roles.manage',
            'communications.manage',
        ]);

        $this->actingAs($admin)->get(route('admin.settings.index'))->assertForbidden();
        $this->actingAs($admin)->get(route('admin.cms.index'))->assertForbidden();
        $this->actingAs($admin)->get(route('admin.security.index'))->assertForbidden();

        $this->actingAs($admin)->post(route('admin.communications.templates.store'), [
            'name' => 'Global Template',
            'slug' => 'global-template',
            'channel' => 'email',
            'body' => 'Test',
            'status' => 1,
        ])->assertForbidden();
    }
}
