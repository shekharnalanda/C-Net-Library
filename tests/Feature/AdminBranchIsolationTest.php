<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Enquiry;
use App\Models\Role;
use App\Models\Student;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminBranchIsolationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_non_global_admin_requires_branch_assignment(): void
    {
        $admin = User::create([
            'name' => 'Branch Admin',
            'email' => 'branch-admin-no-branch@example.com',
            'password' => 'password123',
            'role' => 'admin',
            'status' => true,
        ]);
        $role = Role::where('slug', 'branch-admin')->firstOrFail();
        $admin->roles()->sync([$role->id]);

        $this->actingAs($admin)->get('/admin/dashboard')->assertForbidden();
    }

    public function test_branch_admin_only_sees_students_from_assigned_branch(): void
    {
        [$branchA, $branchB] = $this->branches();
        $admin = $this->branchAdmin($branchA);

        Student::create([
            'branch_id' => $branchA->id,
            'student_code' => 'BR-A-STUDENT',
            'name' => 'Branch A Student',
            'mobile' => '9000000001',
            'joining_date' => today(),
            'status' => 'active',
        ]);
        Student::create([
            'branch_id' => $branchB->id,
            'student_code' => 'BR-B-STUDENT',
            'name' => 'Branch B Student',
            'mobile' => '9000000002',
            'joining_date' => today(),
            'status' => 'active',
        ]);

        $this->actingAs($admin)
            ->get('/admin/students')
            ->assertOk()
            ->assertSee('Branch A Student')
            ->assertDontSee('Branch B Student');
    }

    public function test_branch_admin_cannot_open_cross_branch_student_or_enquiry(): void
    {
        [$branchA, $branchB] = $this->branches();
        $admin = $this->branchAdmin($branchA);

        $student = Student::create([
            'branch_id' => $branchB->id,
            'student_code' => 'BR-B-LOCKED',
            'name' => 'Locked Student',
            'mobile' => '9000000003',
            'joining_date' => today(),
            'status' => 'active',
        ]);

        $enquiry = Enquiry::create([
            'branch_id' => $branchB->id,
            'enquiry_no' => 'ENQ-B-LOCKED',
            'name' => 'Locked Lead',
            'mobile' => '9000000004',
            'source' => 'website',
            'status' => 'new',
        ]);

        $this->actingAs($admin)->get(route('admin.students.show', $student))->assertForbidden();
        $this->actingAs($admin)->patch(route('admin.enquiries.update', $enquiry), [
            'status' => 'contacted',
        ])->assertForbidden();
    }

    public function test_branch_admin_cannot_forge_another_branch_id(): void
    {
        [$branchA, $branchB] = $this->branches();
        $admin = $this->branchAdmin($branchA);

        $this->actingAs($admin)
            ->get('/admin/reports?branch_id='.$branchB->id)
            ->assertForbidden();
    }

    private function branches(): array
    {
        $branchA = Branch::query()->where('status', true)->firstOrFail();
        $branchB = Branch::create([
            'name' => 'Second Branch',
            'code' => 'BR-SECOND',
            'address' => 'Second Branch Address',
            'status' => true,
        ]);

        return [$branchA, $branchB];
    }

    private function branchAdmin(Branch $branch): User
    {
        $admin = User::create([
            'branch_id' => $branch->id,
            'name' => 'Scoped Branch Admin',
            'email' => 'scoped-admin-'.$branch->id.'@example.com',
            'password' => 'password123',
            'role' => 'admin',
            'status' => true,
        ]);
        $role = Role::where('slug', 'branch-admin')->firstOrFail();
        $admin->roles()->sync([$role->id]);

        return $admin;
    }
}
