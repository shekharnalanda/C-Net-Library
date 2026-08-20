<?php

namespace Tests\Feature;

use App\Models\Admission;
use App\Models\Branch;
use App\Models\FeePlan;
use App\Models\Seat;
use App\Models\SeatAllocation;
use App\Models\Student;
use App\Models\StudentMembership;
use App\Models\StudyHall;
use App\Models\StudySlot;
use App\Models\User;
use App\Services\AdmissionApprovalService;
use App\Services\MembershipRenewalService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class RenewalAndAdmissionSafetyTest extends TestCase
{
    use RefreshDatabase;

    public function test_membership_renewal_releases_existing_allocation_on_safe_date(): void
    {
        $branch = Branch::factory()->create(['status' => true]);
        $hall = StudyHall::factory()->create(['branch_id' => $branch->id]);
        $slot = StudySlot::factory()->create([
            'branch_id' => $branch->id,
            'start_time' => '08:00:00',
            'end_time' => '14:00:00',
            'status' => true,
        ]);
        $plan = FeePlan::create([
            'branch_id' => $branch->id,
            'study_slot_id' => $slot->id,
            'name' => 'Monthly',
            'monthly_fee' => 1000,
            'validity_days' => 30,
            'status' => true,
        ]);
        $oldSeat = Seat::create([
            'study_hall_id' => $hall->id,
            'seat_no' => 'A-1',
            'status' => true,
        ]);
        $newSeat = Seat::create([
            'study_hall_id' => $hall->id,
            'seat_no' => 'A-2',
            'status' => true,
        ]);
        $student = Student::create([
            'branch_id' => $branch->id,
            'student_code' => 'REN-'.Str::upper(Str::random(8)),
            'qr_token' => (string) Str::uuid(),
            'name' => 'Renewal Student',
            'mobile' => '9000000011',
            'joining_date' => today(),
            'status' => 'active',
        ]);
        $membership = StudentMembership::create([
            'student_id' => $student->id,
            'fee_plan_id' => $plan->id,
            'study_slot_id' => $slot->id,
            'start_date' => today()->subDays(10),
            'expiry_date' => today()->subDay(),
            'base_fee' => 1000,
            'discount' => 0,
            'final_fee' => 1000,
            'status' => 'active',
        ]);
        $allocation = SeatAllocation::create([
            'student_id' => $student->id,
            'student_membership_id' => $membership->id,
            'seat_id' => $oldSeat->id,
            'study_slot_id' => $slot->id,
            'allocated_from' => today()->subDays(10),
            'allocated_to' => today()->addDays(5),
            'start_time' => $slot->start_time,
            'end_time' => $slot->end_time,
            'status' => 'active',
        ]);

        app(MembershipRenewalService::class)->renew($student, [
            'fee_plan_id' => $plan->id,
            'study_slot_id' => $slot->id,
            'seat_id' => $newSeat->id,
            'start_date' => today()->toDateString(),
            'discount' => 0,
        ]);

        $allocation->refresh();
        $this->assertSame('released', $allocation->status);
        $this->assertSame(today()->subDay()->toDateString(), $allocation->allocated_to?->toDateString());
    }

    public function test_future_renewal_keeps_current_membership_and_seat_active_until_start_date(): void
    {
        $branch = Branch::factory()->create(['status' => true]);
        $hall = StudyHall::factory()->create(['branch_id' => $branch->id]);
        $slot = StudySlot::factory()->create([
            'branch_id' => $branch->id,
            'start_time' => '08:00:00',
            'end_time' => '14:00:00',
            'status' => true,
        ]);
        $plan = FeePlan::create([
            'branch_id' => $branch->id,
            'study_slot_id' => $slot->id,
            'name' => 'Monthly',
            'monthly_fee' => 1000,
            'validity_days' => 30,
            'status' => true,
        ]);
        $oldSeat = Seat::create(['study_hall_id' => $hall->id, 'seat_no' => 'F-1', 'status' => true]);
        $newSeat = Seat::create(['study_hall_id' => $hall->id, 'seat_no' => 'F-2', 'status' => true]);
        $student = Student::create([
            'branch_id' => $branch->id,
            'student_code' => 'FUT-'.Str::upper(Str::random(8)),
            'qr_token' => (string) Str::uuid(),
            'name' => 'Future Renewal Student',
            'mobile' => '9000000111',
            'joining_date' => today()->subMonth(),
            'status' => 'active',
        ]);
        $current = StudentMembership::create([
            'student_id' => $student->id,
            'fee_plan_id' => $plan->id,
            'study_slot_id' => $slot->id,
            'start_date' => today()->subDays(20),
            'expiry_date' => today()->addDays(10),
            'base_fee' => 1000,
            'discount' => 0,
            'final_fee' => 1000,
            'status' => 'active',
        ]);
        $currentAllocation = SeatAllocation::create([
            'student_id' => $student->id,
            'student_membership_id' => $current->id,
            'seat_id' => $oldSeat->id,
            'study_slot_id' => $slot->id,
            'allocated_from' => today()->subDays(20),
            'allocated_to' => today()->addDays(10),
            'start_time' => $slot->start_time,
            'end_time' => $slot->end_time,
            'status' => 'active',
        ]);

        $renewal = app(MembershipRenewalService::class)->renew($student, [
            'fee_plan_id' => $plan->id,
            'study_slot_id' => $slot->id,
            'seat_id' => $newSeat->id,
            'start_date' => today()->toDateString(),
            'discount' => 0,
        ]);

        $this->assertSame('active', $current->fresh()->status);
        $this->assertSame('active', $currentAllocation->fresh()->status);
        $this->assertSame('pending', $renewal->status);
        $this->assertSame(today()->addDays(11)->toDateString(), $renewal->start_date->toDateString());
        $this->assertDatabaseHas('seat_allocations', [
            'student_membership_id' => $renewal->id,
            'seat_id' => $newSeat->id,
            'status' => 'reserved',
        ]);
    }

    public function test_admission_approval_rejects_email_used_by_non_student_account(): void
    {
        $branch = Branch::factory()->create(['status' => true]);
        $hall = StudyHall::factory()->create(['branch_id' => $branch->id]);
        $slot = StudySlot::factory()->create([
            'branch_id' => $branch->id,
            'start_time' => '08:00:00',
            'end_time' => '14:00:00',
            'status' => true,
        ]);
        $plan = FeePlan::create([
            'branch_id' => $branch->id,
            'study_slot_id' => $slot->id,
            'name' => 'Monthly',
            'monthly_fee' => 1000,
            'validity_days' => 30,
            'status' => true,
        ]);
        $seat = Seat::create([
            'study_hall_id' => $hall->id,
            'seat_no' => 'B-1',
            'status' => true,
        ]);

        User::factory()->create([
            'email' => 'staff-collision@example.com',
            'role' => 'admin',
            'status' => true,
        ]);

        $admission = Admission::create([
            'branch_id' => $branch->id,
            'application_no' => 'ADM-'.Str::upper(Str::random(8)),
            'name' => 'Collision Applicant',
            'mobile' => '9000000012',
            'email' => 'staff-collision@example.com',
            'status' => 'pending',
        ]);

        try {
            app(AdmissionApprovalService::class)->approve($admission, [
                'fee_plan_id' => $plan->id,
                'study_slot_id' => $slot->id,
                'seat_id' => $seat->id,
                'start_date' => today()->toDateString(),
                'discount' => 0,
            ]);
            $this->fail('Expected validation exception was not thrown.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('email', $exception->errors());
        }

        $this->assertDatabaseMissing('students', ['email' => 'staff-collision@example.com']);
        $this->assertNotSame('converted', $admission->fresh()->status);
    }
}
