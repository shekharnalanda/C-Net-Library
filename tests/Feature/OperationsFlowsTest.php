<?php

namespace Tests\Feature;

use App\Models\Book;
use App\Models\BookCategory;
use App\Models\BookCopy;
use App\Models\Branch;
use App\Models\FeePlan;
use App\Models\Seat;
use App\Models\SeatAllocation;
use App\Models\Student;
use App\Models\StudentMembership;
use App\Models\StudySlot;
use App\Models\User;
use App\Services\AttendanceService;
use App\Services\LibraryCirculationService;
use App\Services\MembershipRenewalService;
use App\Services\SeatAllocationService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class OperationsFlowsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_same_seat_allows_non_overlapping_slots_and_rejects_overlapping_slot(): void
    {
        $branch = Branch::query()->where('status', true)->firstOrFail();
        $slot = StudySlot::query()->where('branch_id', $branch->id)->where('status', true)->firstOrFail();
        $plan = FeePlan::query()->where('branch_id', $branch->id)->where('study_slot_id', $slot->id)->firstOrFail();
        $seat = Seat::query()->whereHas('studyHall', fn ($q) => $q->where('branch_id', $branch->id))->firstOrFail();
        $student = $this->makeStudent($branch);
        $membership = StudentMembership::create([
            'student_id' => $student->id,
            'fee_plan_id' => $plan->id,
            'study_slot_id' => $slot->id,
            'start_date' => today(),
            'expiry_date' => today()->addDays(10),
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
            'allocated_from' => today()->toDateString(),
            'allocated_to' => today()->addDays(10)->toDateString(),
            'start_time' => '06:00:00',
            'end_time' => '10:00:00',
            'status' => 'active',
        ]);

        $service = app(SeatAllocationService::class);

        $this->assertFalse($service->hasConflict(
            $seat->id,
            today(),
            today()->addDays(5),
            '10:00:00',
            '14:00:00'
        ));

        $this->assertTrue($service->hasConflict(
            $seat->id,
            today(),
            today()->addDays(5),
            '09:00:00',
            '12:00:00'
        ));
    }

    public function test_membership_renewal_schedules_future_membership_and_reserves_seat(): void
    {
        $branch = Branch::query()->where('status', true)->firstOrFail();
        $slot = StudySlot::query()->where('branch_id', $branch->id)->where('status', true)->firstOrFail();
        $plan = FeePlan::query()->where('branch_id', $branch->id)->where('study_slot_id', $slot->id)->firstOrFail();
        $seat = Seat::query()->whereHas('studyHall', fn ($q) => $q->where('branch_id', $branch->id))->firstOrFail();
        $student = $this->makeStudent($branch);

        $oldMembership = StudentMembership::create([
            'student_id' => $student->id,
            'fee_plan_id' => $plan->id,
            'study_slot_id' => $slot->id,
            'start_date' => today()->subDays(29),
            'expiry_date' => today(),
            'base_fee' => $plan->monthly_fee,
            'discount' => 0,
            'final_fee' => $plan->monthly_fee,
            'status' => 'active',
        ]);

        SeatAllocation::create([
            'student_id' => $student->id,
            'student_membership_id' => $oldMembership->id,
            'seat_id' => $seat->id,
            'study_slot_id' => $slot->id,
            'allocated_from' => today()->subDays(29),
            'allocated_to' => today(),
            'start_time' => $slot->start_time,
            'end_time' => $slot->end_time,
            'status' => 'active',
        ]);

        $newMembership = app(MembershipRenewalService::class)->renew($student, [
            'fee_plan_id' => $plan->id,
            'study_slot_id' => $slot->id,
            'seat_id' => $seat->id,
            'discount' => 0,
        ]);

        $this->assertSame('active', $oldMembership->fresh()->status);
        $this->assertSame(today()->addDay()->toDateString(), $newMembership->start_date->toDateString());
        $this->assertSame('pending', $newMembership->status);
        $this->assertDatabaseHas('seat_allocations', [
            'student_membership_id' => $newMembership->id,
            'seat_id' => $seat->id,
            'status' => 'reserved',
        ]);
    }

    public function test_attendance_prevents_duplicate_open_session_and_records_study_minutes(): void
    {
        $branch = Branch::query()->where('status', true)->firstOrFail();
        $slot = StudySlot::query()->where('branch_id', $branch->id)->where('status', true)->firstOrFail();
        $plan = FeePlan::query()->where('branch_id', $branch->id)->where('study_slot_id', $slot->id)->firstOrFail();
        $student = $this->makeStudent($branch);
        $admin = User::query()->where('role', 'super_admin')->firstOrFail();

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
        $attendance = $service->checkIn($student, $admin->id);

        try {
            $service->checkIn($student, $admin->id);
            $this->fail('Expected duplicate check-in to be rejected.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('student', $exception->errors());
        }

        $attendance->update(['check_in_at' => now()->subMinutes(90)]);
        $checkedOut = $service->checkOut($student, $admin->id);

        $this->assertNotNull($checkedOut->check_out_at);
        $this->assertGreaterThanOrEqual(89, $checkedOut->study_minutes);
    }

    public function test_library_issue_and_return_updates_copy_status_and_calculates_fine(): void
    {
        $branch = Branch::query()->where('status', true)->firstOrFail();
        $student = $this->makeStudent($branch);
        $admin = User::query()->where('role', 'super_admin')->firstOrFail();

        $category = BookCategory::create([
            'name' => 'Test Category',
            'slug' => 'test-category',
            'status' => true,
        ]);
        $book = Book::create([
            'book_category_id' => $category->id,
            'title' => 'Integration Test Book',
            'status' => true,
        ]);
        $copy = BookCopy::create([
            'book_id' => $book->id,
            'branch_id' => $branch->id,
            'accession_no' => 'TEST-ACC-001',
            'condition' => 'good',
            'status' => 'available',
        ]);

        $service = app(LibraryCirculationService::class);
        $issue = $service->issue($student, $copy, 1, $admin->id);

        $this->assertSame('issued', $copy->fresh()->status);
        $issue->update(['due_at' => today()->subDays(2)]);

        $returned = $service->return($issue->fresh(), 5, $admin->id);

        $this->assertSame('returned', $returned->status);
        $this->assertSame('available', $copy->fresh()->status);
        $this->assertSame('10.00', $returned->fine_amount);
    }

    private function makeStudent(Branch $branch): Student
    {
        static $counter = 0;
        $counter++;

        return Student::create([
            'branch_id' => $branch->id,
            'student_code' => 'CNL-OPS-'.$counter,
            'name' => 'Operations Student '.$counter,
            'mobile' => '9000000'.str_pad((string) $counter, 3, '0', STR_PAD_LEFT),
            'joining_date' => today(),
            'status' => 'active',
        ]);
    }
}
