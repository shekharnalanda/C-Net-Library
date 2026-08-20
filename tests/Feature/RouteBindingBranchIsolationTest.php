<?php

namespace Tests\Feature;

use App\Models\Admission;
use App\Models\Branch;
use App\Models\DigitalResource;
use App\Models\Role;
use App\Models\Student;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class RouteBindingBranchIsolationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_branch_admin_cannot_read_or_rotate_other_branch_student(): void
    {
        [$admin, , $otherBranch] = $this->branchAdminFixture();
        $student = Student::create([
            'branch_id' => $otherBranch->id,
            'student_code' => 'ISO-'.Str::upper(Str::random(8)),
            'qr_token' => (string) Str::uuid(),
            'name' => 'Other Branch Student',
            'mobile' => '9000000771',
            'joining_date' => today(),
            'status' => 'active',
        ]);

        $this->actingAs($admin)->get(route('admin.students.show', $student))->assertForbidden();
        $this->actingAs($admin)->post(route('admin.students.rotate-qr', $student))->assertForbidden();
    }

    public function test_branch_admin_cannot_read_other_branch_admission(): void
    {
        [$admin, , $otherBranch] = $this->branchAdminFixture();
        $admission = Admission::create([
            'branch_id' => $otherBranch->id,
            'application_no' => 'ISO-ADM-'.Str::upper(Str::random(8)),
            'name' => 'Other Branch Applicant',
            'mobile' => '9000000772',
            'status' => 'new',
        ]);

        $this->actingAs($admin)->get(route('admin.admissions.show', $admission))->assertForbidden();
    }

    public function test_branch_admin_cannot_update_or_delete_other_branch_digital_resource(): void
    {
        [$admin, , $otherBranch] = $this->branchAdminFixture();
        $resource = DigitalResource::create([
            'branch_id' => $otherBranch->id,
            'title' => 'Other Branch Link',
            'slug' => 'other-branch-link-'.Str::lower(Str::random(6)),
            'resource_type' => 'link',
            'external_url' => 'https://example.com/resource',
            'access_type' => 'public',
            'download_allowed' => false,
            'status' => true,
        ]);

        $this->actingAs($admin)->patch(route('admin.digital-resources.update', $resource), [
            'title' => 'Attempted Update',
            'resource_type' => 'link',
            'external_url' => 'https://example.com/resource',
            'access_type' => 'public',
            'status' => true,
        ])->assertForbidden();

        $this->actingAs($admin)->delete(route('admin.digital-resources.destroy', $resource))->assertForbidden();
    }

    private function branchAdminFixture(): array
    {
        $branches = Branch::query()->where('status', true)->limit(2)->get();
        while ($branches->count() < 2) {
            $branches->push(Branch::factory()->create(['status' => true]));
        }

        $ownBranch = $branches->first();
        $otherBranch = $branches->last();
        $admin = User::factory()->create([
            'branch_id' => $ownBranch->id,
            'role' => 'admin',
            'status' => true,
        ]);

        $role = Role::query()->where('slug', 'branch-admin')->firstOrFail();
        $admin->roles()->syncWithoutDetaching([$role->id]);

        return [$admin, $ownBranch, $otherBranch];
    }
}
