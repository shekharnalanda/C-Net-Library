<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\FeePlan;
use App\Models\Student;
use App\Models\StudentMembership;
use App\Models\StudySlot;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class SchemaConsistencyRegressionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_due_pending_membership_for_inactive_student_is_not_activated(): void
    {
        $branch = Branch::query()->where('status', true)->firstOrFail();
        $slot = StudySlot::query()->where('branch_id', $branch->id)->where('status', true)->firstOrFail();
        $plan = FeePlan::query()->where('branch_id', $branch->id)->where('status', true)->firstOrFail();

        $student = Student::create([
            'branch_id' => $branch->id,
            'student_code' => 'INACTIVE-'.Str::upper(Str::random(6)),
            'qr_token' => (string) Str::uuid(),
            'name' => 'Inactive Scheduled Student',
            'mobile' => '9000000991',
            'joining_date' => today()->subMonth(),
            'status' => 'inactive',
        ]);

        $membership = StudentMembership::create([
            'student_id' => $student->id,
            'fee_plan_id' => $plan->id,
            'study_slot_id' => $slot->id,
            'start_date' => today(),
            'expiry_date' => today()->addDays(29),
            'base_fee' => 1000,
            'discount' => 0,
            'final_fee' => 1000,
            'status' => 'pending',
        ]);

        $this->artisan('memberships:activate-scheduled')->assertExitCode(1);

        $this->assertSame('pending', $membership->fresh()->status);
    }

    public function test_branch_admin_qr_lookup_does_not_reveal_other_branch_student(): void
    {
        $branches = Branch::query()->where('status', true)->limit(2)->get();
        if ($branches->count() < 2) {
            $branches->push(Branch::factory()->create(['status' => true]));
        }

        $ownBranch = $branches->first();
        $otherBranch = $branches->last();

        $admin = User::factory()->create([
            'branch_id' => $ownBranch->id,
            'role' => 'admin',
            'status' => true,
        ]);

        $student = Student::create([
            'branch_id' => $otherBranch->id,
            'student_code' => 'OTHER-'.Str::upper(Str::random(6)),
            'qr_token' => (string) Str::uuid(),
            'name' => 'Other Branch Student',
            'mobile' => '9000000992',
            'joining_date' => today(),
            'status' => 'active',
        ]);

        $response = $this->actingAs($admin)->post(route('admin.attendance.scan.lookup'), [
            'token' => $student->qr_token,
        ]);

        $response->assertOk();
        $response->assertSee('No student found for this QR token.');
        $response->assertDontSee($student->student_code);
    }
}
