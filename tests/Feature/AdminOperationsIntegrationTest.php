<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Enquiry;
use App\Models\FeePlan;
use App\Models\Seat;
use App\Models\SeatAllocation;
use App\Models\Staff;
use App\Models\Student;
use App\Models\StudentMembership;
use App\Models\StudySlot;
use App\Models\User;
use App\Services\AttendanceService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class AdminOperationsIntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_qr_cooldown_rejects_immediate_repeat_scan(): void
    {
        $branch = Branch::query()->where('status', true)->firstOrFail();
        $slot = StudySlot::query()->where('branch_id', $branch->id)->where('status', true)->firstOrFail();
        $plan = FeePlan::query()->where('branch_id', $branch->id)->where('study_slot_id', $slot->id)->firstOrFail();
        $admin = User::query()->where('role', 'super_admin')->firstOrFail();

        $student = Student::create([
            'branch_id' => $branch->id,
            'student_code' => 'CNL-QR-TEST',
            'name' => 'QR Test Student',
            'mobile' => '9011111111',
            'joining_date' => today(),
            'status' => 'active',
        ]);

        StudentMembership::create([
            'student_id' => $student->id,
            'fee_plan_id' => $plan->id,
            'study_slot_id' => $slot->id,
            'start_date' => today()->subDay(),
            'expiry_date' => today()->addDays(30),
            'base_fee' => $plan->monthly_fee,
            'discount' => 0,
            'final_fee' => $plan->monthly_fee,
            'status' => 'active',
        ]);

        $service = app(AttendanceService::class);
        $service->checkIn($student, $admin->id, 'qr');

        $this->expectException(ValidationException::class);
        $service->checkOut($student, $admin->id, null, 'qr');
    }

    public function test_seat_availability_endpoint_excludes_conflicting_seat(): void
    {
        $branch = Branch::query()->where('status', true)->firstOrFail();
        $slot = StudySlot::query()->where('branch_id', $branch->id)->where('status', true)->firstOrFail();
        $plan = FeePlan::query()->where('branch_id', $branch->id)->where('study_slot_id', $slot->id)->firstOrFail();
        $seat = Seat::query()->whereHas('studyHall', fn ($q) => $q->where('branch_id', $branch->id))->firstOrFail();
        $admin = User::query()->where('role', 'super_admin')->firstOrFail();

        $student = Student::create([
            'branch_id' => $branch->id,
            'student_code' => 'CNL-SEAT-TEST',
            'name' => 'Seat Test Student',
            'mobile' => '9022222222',
            'joining_date' => today(),
            'status' => 'active',
        ]);

        $membership = StudentMembership::create([
            'student_id' => $student->id,
            'fee_plan_id' => $plan->id,
            'study_slot_id' => $slot->id,
            'start_date' => today(),
            'expiry_date' => today()->addDays(30),
            'base_fee' => $plan->monthly_fee,
            'discount' => 0,
            'final_fee' => $plan->monthly_fee,
            'status' => 'active',
        ]);

        SeatAllocation::create([
            'student_id' => $student->id,
            'student_membership_id' => $membership->id,
            'seat_id' => $seat->id,
            'study_slot_id' => $slot->id,
            'allocated_from' => today(),
            'allocated_to' => today()->addDays(30),
            'start_time' => $slot->start_time,
            'end_time' => $slot->end_time,
            'status' => 'active',
        ]);

        $response = $this->actingAs($admin)->getJson('/admin/available-seats?'.http_build_query([
            'branch_id' => $branch->id,
            'study_slot_id' => $slot->id,
            'allocated_from' => today()->toDateString(),
            'allocated_to' => today()->addDays(5)->toDateString(),
        ]));

        $response->assertOk();
        $this->assertFalse(collect($response->json())->contains(fn ($item) => (int) $item['id'] === $seat->id));
    }

    public function test_enquiry_can_be_converted_to_admission(): void
    {
        $branch = Branch::query()->where('status', true)->firstOrFail();
        $admin = User::query()->where('role', 'super_admin')->firstOrFail();

        $enquiry = Enquiry::create([
            'branch_id' => $branch->id,
            'enquiry_no' => 'CNL-ENQ-INTEGRATION',
            'name' => 'CRM Student',
            'mobile' => '9033333333',
            'email' => 'crm-integration@example.com',
            'status' => 'qualified',
        ]);

        $response = $this->actingAs($admin)->post('/admin/enquiries/'.$enquiry->id.'/convert');

        $enquiry->refresh();
        $response->assertRedirect('/admin/admissions/'.$enquiry->converted_admission_id);

        $this->assertSame('converted', $enquiry->status);
        $this->assertNotNull($enquiry->converted_admission_id);
        $this->assertDatabaseHas('admissions', [
            'id' => $enquiry->converted_admission_id,
            'branch_id' => $branch->id,
            'name' => 'CRM Student',
            'mobile' => '9033333333',
            'status' => 'pending',
        ]);
    }

    public function test_staff_payroll_can_be_created_and_marked_paid(): void
    {
        $branch = Branch::query()->where('status', true)->firstOrFail();
        $admin = User::query()->where('role', 'super_admin')->firstOrFail();

        $staff = Staff::create([
            'branch_id' => $branch->id,
            'staff_code' => 'CNL-STF-TEST',
            'name' => 'Payroll Test Staff',
            'role' => 'librarian',
            'monthly_salary' => 15000,
            'status' => 'active',
        ]);

        $response = $this->actingAs($admin)->post('/admin/staff/'.$staff->id.'/payroll', [
            'month' => 8,
            'year' => 2026,
            'allowances' => 1000,
            'deductions' => 500,
            'status' => 'paid',
            'payment_mode' => 'bank_transfer',
            'transaction_ref' => 'PAYROLL-TEST-001',
        ]);

        $response->assertRedirect();

        $this->assertDatabaseHas('payrolls', [
            'staff_id' => $staff->id,
            'month' => 8,
            'year' => 2026,
            'basic_salary' => '15000.00',
            'allowances' => '1000.00',
            'deductions' => '500.00',
            'net_salary' => '15500.00',
            'status' => 'paid',
            'transaction_ref' => 'PAYROLL-TEST-001',
            'processed_by' => $admin->id,
        ]);
    }
}
