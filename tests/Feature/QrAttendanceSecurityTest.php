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

class QrAttendanceSecurityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_scanner_page_does_not_take_token_from_query_string(): void
    {
        $admin = User::query()->where('role', 'super_admin')->firstOrFail();
        $secret = (string) Str::uuid();

        $response = $this->actingAs($admin)->get(route('admin.attendance.scan', ['token' => $secret]));

        $response->assertOk();
        $response->assertDontSee($secret);
        $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
        $this->assertSame('no-referrer', $response->headers->get('Referrer-Policy'));
    }

    public function test_qr_lookup_is_post_only_and_does_not_echo_token_as_text(): void
    {
        $admin = User::query()->where('role', 'super_admin')->firstOrFail();
        [$student] = $this->createStudent('active');

        $response = $this->actingAs($admin)->post(route('admin.attendance.scan.lookup'), [
            'token' => $student->qr_token,
        ]);

        $response->assertOk();
        $response->assertSee($student->student_code);
        $response->assertDontSee('value="'.$student->qr_token.'"', false);
        $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
    }

    public function test_inactive_student_cannot_check_in_by_qr(): void
    {
        $admin = User::query()->where('role', 'super_admin')->firstOrFail();
        [$student] = $this->createStudent('inactive');

        $response = $this->actingAs($admin)
            ->from(route('admin.attendance.scan'))
            ->post(route('admin.attendance.scan.mark', $student), [
                'token' => $student->qr_token,
                'action' => 'check_in',
            ]);

        $response->assertSessionHasErrors('student');
        $this->assertDatabaseMissing('attendances', ['student_id' => $student->id]);
    }

    public function test_rotating_qr_invalidates_previous_token(): void
    {
        $admin = User::query()->where('role', 'super_admin')->firstOrFail();
        [$student] = $this->createStudent('active');
        $oldToken = $student->qr_token;

        $this->actingAs($admin)
            ->post(route('admin.students.rotate-qr', $student))
            ->assertSessionHas('success');

        $student->refresh();
        $this->assertNotSame($oldToken, $student->qr_token);

        $this->actingAs($admin)
            ->post(route('admin.attendance.scan.mark', $student), [
                'token' => $oldToken,
                'action' => 'check_in',
            ])
            ->assertForbidden();
    }

    private function createStudent(string $status): array
    {
        $branch = Branch::query()->where('status', true)->firstOrFail();
        $slot = StudySlot::query()->where('branch_id', $branch->id)->where('status', true)->first();

        if (! $slot) {
            $slot = StudySlot::create([
                'branch_id' => $branch->id,
                'name' => 'QR Test Slot',
                'start_time' => '08:00:00',
                'end_time' => '14:00:00',
                'status' => true,
            ]);
        }

        $plan = FeePlan::query()->where('branch_id', $branch->id)->first();
        if (! $plan) {
            $plan = FeePlan::create([
                'branch_id' => $branch->id,
                'study_slot_id' => $slot->id,
                'name' => 'QR Test Plan',
                'monthly_fee' => 1000,
                'validity_days' => 30,
                'status' => true,
            ]);
        }

        $student = Student::create([
            'branch_id' => $branch->id,
            'student_code' => 'QR-'.Str::upper(Str::random(8)),
            'qr_token' => (string) Str::uuid(),
            'name' => 'QR Student',
            'mobile' => '9'.random_int(100000000, 999999999),
            'joining_date' => today(),
            'status' => $status,
        ]);

        StudentMembership::create([
            'student_id' => $student->id,
            'fee_plan_id' => $plan->id,
            'study_slot_id' => $slot->id,
            'start_date' => today()->subDay(),
            'expiry_date' => today()->addDays(10),
            'base_fee' => 1000,
            'discount' => 0,
            'final_fee' => 1000,
            'status' => 'active',
        ]);

        return [$student, $branch];
    }
}
