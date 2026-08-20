<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\FeePlan;
use App\Models\Role;
use App\Models\Student;
use App\Models\StudentMembership;
use App\Models\StudySlot;
use App\Models\User;
use App\Services\AttendanceService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class QrAttendanceFlowIntegrityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_static_qr_landing_redirects_to_tokenless_scanner_and_creates_one_time_challenge(): void
    {
        [$admin, $student] = $this->fixtures();

        $landing = $this->actingAs($admin)->get(route('admin.attendance.qr', ['token' => $student->qr_token]));
        $landing->assertRedirect(route('admin.attendance.scan'));

        $scanner = $this->actingAs($admin)->get(route('admin.attendance.scan'));
        $scanner->assertOk();
        $scanner->assertSee($student->student_code);
        $scanner->assertDontSee((string) $student->qr_token);
        $scanner->assertSee('name="challenge"', false);
    }

    public function test_attendance_challenge_is_single_use(): void
    {
        [$admin, $student] = $this->fixtures();

        $lookup = $this->actingAs($admin)->post(route('admin.attendance.scan.lookup'), [
            'token' => $student->qr_token,
        ]);
        $lookup->assertOk();

        $content = $lookup->getContent();
        preg_match('/name="challenge" value="([a-f0-9]{64})"/', $content, $matches);
        $this->assertNotEmpty($matches[1] ?? null);
        $challenge = $matches[1];

        $this->actingAs($admin)->post(route('admin.attendance.scan.mark', $student), [
            'challenge' => $challenge,
            'action' => 'check_in',
        ])->assertRedirect(route('admin.attendance.scan'));

        $this->actingAs($admin)->post(route('admin.attendance.scan.mark', $student), [
            'challenge' => $challenge,
            'action' => 'check_in',
        ])->assertForbidden();
    }

    public function test_branch_admin_qr_landing_does_not_reveal_other_branch_student(): void
    {
        [$admin] = $this->fixtures();
        $otherBranch = Branch::factory()->create(['status' => true]);
        $student = Student::create([
            'branch_id' => $otherBranch->id,
            'student_code' => 'QR-OTHER-'.Str::upper(Str::random(6)),
            'qr_token' => (string) Str::uuid(),
            'name' => 'Other Branch QR Student',
            'mobile' => '9000098112',
            'joining_date' => today(),
            'status' => 'active',
        ]);

        $this->actingAs($admin)->get(route('admin.attendance.qr', ['token' => $student->qr_token]))
            ->assertRedirect(route('admin.attendance.scan'));

        $this->actingAs($admin)->get(route('admin.attendance.scan'))
            ->assertOk()
            ->assertDontSee($student->student_code)
            ->assertDontSee((string) $student->qr_token);
    }

    public function test_second_check_in_is_rejected_while_session_is_open(): void
    {
        [$admin, $student] = $this->fixtures();
        $service = app(AttendanceService::class);

        $service->checkIn($student, $admin->id, 'manual', 'First check-in');

        try {
            $service->checkIn($student, $admin->id, 'manual', 'Duplicate check-in');
            $this->fail('Expected duplicate open attendance validation error.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('student', $exception->errors());
        }

        $this->assertSame(1, $student->attendances()->whereNull('check_out_at')->count());
    }

    private function fixtures(): array
    {
        $branch = Branch::factory()->create(['status' => true]);
        $admin = User::factory()->create([
            'branch_id' => $branch->id,
            'role' => 'admin',
            'status' => true,
        ]);
        $branchAdminRole = Role::query()->where('slug', 'branch-admin')->firstOrFail();
        $admin->roles()->syncWithoutDetaching([$branchAdminRole->id]);

        $slot = StudySlot::factory()->create([
            'branch_id' => $branch->id,
            'status' => true,
        ]);
        $plan = FeePlan::create([
            'branch_id' => $branch->id,
            'study_slot_id' => $slot->id,
            'name' => 'QR Test Plan',
            'monthly_fee' => 1000,
            'validity_days' => 30,
            'status' => true,
        ]);
        $student = Student::create([
            'branch_id' => $branch->id,
            'student_code' => 'QR-'.Str::upper(Str::random(8)),
            'qr_token' => (string) Str::uuid(),
            'name' => 'QR Flow Student',
            'mobile' => '9000098111',
            'joining_date' => today(),
            'status' => 'active',
        ]);
        StudentMembership::create([
            'student_id' => $student->id,
            'fee_plan_id' => $plan->id,
            'study_slot_id' => $slot->id,
            'start_date' => today(),
            'expiry_date' => today()->addDays(29),
            'base_fee' => 1000,
            'discount' => 0,
            'final_fee' => 1000,
            'status' => 'active',
        ]);

        return [$admin, $student];
    }
}
