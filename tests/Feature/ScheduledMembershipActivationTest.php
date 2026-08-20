<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\FeePlan;
use App\Models\Seat;
use App\Models\SeatAllocation;
use App\Models\Student;
use App\Models\StudentMembership;
use App\Models\StudyHall;
use App\Models\StudySlot;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class ScheduledMembershipActivationTest extends TestCase
{
    use RefreshDatabase;

    public function test_due_pending_membership_is_not_activated_when_reserved_seat_has_new_conflict(): void
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
            'seat_no' => 'S-1',
            'status' => true,
        ]);

        $student = Student::create([
            'branch_id' => $branch->id,
            'student_code' => 'SCH-'.Str::upper(Str::random(8)),
            'qr_token' => (string) Str::uuid(),
            'name' => 'Scheduled Student',
            'mobile' => '9000090001',
            'joining_date' => today()->subMonth(),
            'status' => 'active',
        ]);

        $current = StudentMembership::create([
            'student_id' => $student->id,
            'fee_plan_id' => $plan->id,
            'study_slot_id' => $slot->id,
            'start_date' => today()->subMonth(),
            'expiry_date' => today(),
            'base_fee' => 1000,
            'discount' => 0,
            'final_fee' => 1000,
            'status' => 'active',
        ]);

        $currentAllocation = SeatAllocation::create([
            'student_id' => $student->id,
            'student_membership_id' => $current->id,
            'seat_id' => $seat->id,
            'study_slot_id' => $slot->id,
            'allocated_from' => today()->subMonth(),
            'allocated_to' => today(),
            'start_time' => '14:00:00',
            'end_time' => '20:00:00',
            'status' => 'active',
        ]);

        $pending = StudentMembership::create([
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

        $reserved = SeatAllocation::create([
            'student_id' => $student->id,
            'student_membership_id' => $pending->id,
            'seat_id' => $seat->id,
            'study_slot_id' => $slot->id,
            'allocated_from' => today(),
            'allocated_to' => today()->addDays(29),
            'start_time' => '08:00:00',
            'end_time' => '14:00:00',
            'status' => 'reserved',
        ]);

        $otherStudent = Student::create([
            'branch_id' => $branch->id,
            'student_code' => 'SCH-'.Str::upper(Str::random(8)),
            'qr_token' => (string) Str::uuid(),
            'name' => 'Conflicting Student',
            'mobile' => '9000090002',
            'joining_date' => today(),
            'status' => 'active',
        ]);
        $otherMembership = StudentMembership::create([
            'student_id' => $otherStudent->id,
            'fee_plan_id' => $plan->id,
            'study_slot_id' => $slot->id,
            'start_date' => today(),
            'expiry_date' => today()->addDays(29),
            'base_fee' => 1000,
            'discount' => 0,
            'final_fee' => 1000,
            'status' => 'active',
        ]);
        SeatAllocation::create([
            'student_id' => $otherStudent->id,
            'student_membership_id' => $otherMembership->id,
            'seat_id' => $seat->id,
            'study_slot_id' => $slot->id,
            'allocated_from' => today(),
            'allocated_to' => today()->addDays(29),
            'start_time' => '08:00:00',
            'end_time' => '14:00:00',
            'status' => 'active',
        ]);

        $this->artisan('memberships:activate-scheduled')->assertExitCode(1);

        $this->assertSame('active', $current->fresh()->status);
        $this->assertSame('active', $currentAllocation->fresh()->status);
        $this->assertSame('pending', $pending->fresh()->status);
        $this->assertSame('reserved', $reserved->fresh()->status);
    }
}
