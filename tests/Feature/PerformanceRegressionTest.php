<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\FeePlan;
use App\Models\Payment;
use App\Models\PaymentAdjustment;
use App\Models\Seat;
use App\Models\SeatAllocation;
use App\Models\Student;
use App\Models\StudentMembership;
use App\Models\StudyHall;
use App\Models\StudySlot;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PerformanceRegressionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_available_seats_excludes_overlapping_allocations_but_keeps_non_overlapping_shift(): void
    {
        $branch = Branch::query()->where('status', true)->firstOrFail();
        $hall = StudyHall::factory()->create(['branch_id' => $branch->id, 'status' => true]);
        $slot = StudySlot::factory()->create([
            'branch_id' => $branch->id,
            'start_time' => '08:00:00',
            'end_time' => '12:00:00',
            'status' => true,
        ]);
        $otherSlot = StudySlot::factory()->create([
            'branch_id' => $branch->id,
            'start_time' => '14:00:00',
            'end_time' => '18:00:00',
            'status' => true,
        ]);

        $blockedSeat = Seat::factory()->create(['study_hall_id' => $hall->id, 'status' => true]);
        $freeSeat = Seat::factory()->create(['study_hall_id' => $hall->id, 'status' => true]);
        $shiftReusableSeat = Seat::factory()->create(['study_hall_id' => $hall->id, 'status' => true]);

        $student = Student::factory()->create(['branch_id' => $branch->id]);
        $membership = StudentMembership::factory()->create([
            'student_id' => $student->id,
            'study_slot_id' => $slot->id,
            'status' => 'active',
        ]);

        SeatAllocation::create([
            'student_id' => $student->id,
            'student_membership_id' => $membership->id,
            'seat_id' => $blockedSeat->id,
            'study_slot_id' => $slot->id,
            'allocated_from' => today()->toDateString(),
            'allocated_to' => today()->addDays(30)->toDateString(),
            'start_time' => '08:00:00',
            'end_time' => '12:00:00',
            'status' => 'active',
        ]);

        SeatAllocation::create([
            'student_id' => $student->id,
            'student_membership_id' => $membership->id,
            'seat_id' => $shiftReusableSeat->id,
            'study_slot_id' => $otherSlot->id,
            'allocated_from' => today()->toDateString(),
            'allocated_to' => today()->addDays(30)->toDateString(),
            'start_time' => '14:00:00',
            'end_time' => '18:00:00',
            'status' => 'active',
        ]);

        $admin = User::factory()->create([
            'branch_id' => $branch->id,
            'role' => 'super_admin',
            'status' => true,
        ]);

        $response = $this->actingAs($admin)->getJson(route('admin.seats.available', [
            'branch_id' => $branch->id,
            'study_slot_id' => $slot->id,
            'allocated_from' => today()->toDateString(),
            'allocated_to' => today()->addDays(30)->toDateString(),
        ]));

        $response->assertOk();
        $ids = collect($response->json())->pluck('id');

        $this->assertFalse($ids->contains($blockedSeat->id));
        $this->assertTrue($ids->contains($freeSeat->id));
        $this->assertTrue($ids->contains($shiftReusableSeat->id));
    }

    public function test_reports_due_uses_net_paid_after_adjustments(): void
    {
        $branch = Branch::query()->where('status', true)->firstOrFail();
        $student = Student::factory()->create(['branch_id' => $branch->id, 'status' => 'active']);
        $plan = FeePlan::factory()->create(['branch_id' => $branch->id, 'monthly_fee' => 1000, 'status' => true]);
        $membership = StudentMembership::factory()->create([
            'student_id' => $student->id,
            'fee_plan_id' => $plan->id,
            'base_fee' => 1000,
            'discount' => 0,
            'final_fee' => 1000,
            'status' => 'active',
        ]);
        $payment = Payment::factory()->create([
            'student_id' => $student->id,
            'student_membership_id' => $membership->id,
            'amount' => 800,
            'payment_status' => 'partial',
            'payment_date' => today(),
        ]);
        PaymentAdjustment::create([
            'payment_id' => $payment->id,
            'type' => 'refund',
            'amount' => 300,
            'reason' => 'Test refund',
        ]);

        $admin = User::factory()->create([
            'role' => 'super_admin',
            'status' => true,
        ]);

        $response = $this->actingAs($admin)->get(route('admin.reports.index'));

        $response->assertOk();
        $response->assertViewHas('metrics', fn (array $metrics) => (float) $metrics['due'] === 500.0);
    }
}
