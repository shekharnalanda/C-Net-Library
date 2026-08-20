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

class MembershipExpiryLifecycleTest extends TestCase
{
    use RefreshDatabase;

    public function test_expired_active_membership_is_closed_and_its_active_seat_is_released(): void
    {
        [$student, $membership, $allocation] = $this->membershipFixtures(today()->subDay());

        $this->artisan('memberships:expire-due')->assertExitCode(0);

        $this->assertSame('expired', $membership->fresh()->status);
        $this->assertSame('released', $allocation->fresh()->status);
        $this->assertSame($membership->expiry_date->toDateString(), $allocation->fresh()->allocated_to?->toDateString());
        $this->assertNull($student->fresh()->activeMembership);
    }

    public function test_membership_expiring_today_remains_active_until_end_of_today(): void
    {
        [$student, $membership, $allocation] = $this->membershipFixtures(today());

        $this->artisan('memberships:expire-due')->assertExitCode(0);

        $this->assertSame('active', $membership->fresh()->status);
        $this->assertSame('active', $allocation->fresh()->status);
        $this->assertNotNull($student->fresh()->activeMembership);
    }

    public function test_date_aware_relation_ignores_stale_active_row_past_expiry(): void
    {
        [$student, $membership] = $this->membershipFixtures(today()->subDay());

        $this->assertSame('active', $membership->fresh()->status);
        $this->assertNull($student->fresh()->activeMembership);
    }

    private function membershipFixtures($expiryDate): array
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
            'name' => 'Expiry Test',
            'monthly_fee' => 1000,
            'validity_days' => 30,
            'status' => true,
        ]);
        $seat = Seat::create([
            'study_hall_id' => $hall->id,
            'seat_no' => 'EXP-'.Str::upper(Str::random(5)),
            'status' => true,
        ]);
        $student = Student::create([
            'branch_id' => $branch->id,
            'student_code' => 'EXP-'.Str::upper(Str::random(8)),
            'qr_token' => (string) Str::uuid(),
            'name' => 'Expiry Student',
            'mobile' => '9'.fake()->unique()->numerify('#########'),
            'joining_date' => today()->subMonth(),
            'status' => 'active',
        ]);
        $membership = StudentMembership::create([
            'student_id' => $student->id,
            'fee_plan_id' => $plan->id,
            'study_slot_id' => $slot->id,
            'start_date' => today()->subDays(29),
            'expiry_date' => $expiryDate,
            'base_fee' => 1000,
            'discount' => 0,
            'final_fee' => 1000,
            'status' => 'active',
        ]);
        $allocation = SeatAllocation::create([
            'student_id' => $student->id,
            'student_membership_id' => $membership->id,
            'seat_id' => $seat->id,
            'study_slot_id' => $slot->id,
            'allocated_from' => $membership->start_date,
            'allocated_to' => $expiryDate,
            'start_time' => $slot->start_time,
            'end_time' => $slot->end_time,
            'status' => 'active',
        ]);

        return [$student, $membership, $allocation];
    }
}
