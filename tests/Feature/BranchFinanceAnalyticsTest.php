<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Expense;
use App\Models\Payment;
use App\Models\Student;
use App\Models\StudentMembership;
use App\Models\StudySlot;
use App\Models\FeePlan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BranchFinanceAnalyticsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Branch::factory()->create(['status' => true]);
        User::factory()->create([
            'role' => 'super_admin',
            'status' => true,
        ]);
    }

    public function test_reports_filter_finance_and_students_by_branch(): void
    {
        $admin = User::query()->where('role', 'super_admin')->firstOrFail();
        $branchA = Branch::query()->where('status', true)->firstOrFail();
        $branchB = Branch::create(['name' => 'Branch B', 'code' => 'BR-B', 'status' => true]);

        [$studentA, $membershipA] = $this->studentWithMembership($branchA, 'BR-A-STU');
        [$studentB, $membershipB] = $this->studentWithMembership($branchB, 'BR-B-STU');

        Payment::create([
            'student_id' => $studentA->id,
            'student_membership_id' => $membershipA->id,
            'receipt_no' => 'TEST-A-1',
            'amount' => 1000,
            'discount' => 0,
            'late_fee' => 0,
            'payment_date' => today(),
            'payment_mode' => 'cash',
            'payment_status' => 'paid',
            'received_by' => $admin->id,
        ]);

        Payment::create([
            'student_id' => $studentB->id,
            'student_membership_id' => $membershipB->id,
            'receipt_no' => 'TEST-B-1',
            'amount' => 2000,
            'discount' => 0,
            'late_fee' => 0,
            'payment_date' => today(),
            'payment_mode' => 'cash',
            'payment_status' => 'paid',
            'received_by' => $admin->id,
        ]);

        Expense::create([
            'branch_id' => $branchA->id,
            'expense_date' => today(),
            'category' => 'Electricity',
            'amount' => 250,
            'payment_mode' => 'cash',
            'created_by' => $admin->id,
        ]);
        Expense::create([
            'branch_id' => $branchB->id,
            'expense_date' => today(),
            'category' => 'Rent',
            'amount' => 500,
            'payment_mode' => 'cash',
            'created_by' => $admin->id,
        ]);

        $response = $this->actingAs($admin)->get(route('admin.reports.index', [
            'from' => today()->toDateString(),
            'to' => today()->toDateString(),
            'branch_id' => $branchA->id,
        ]));

        $response->assertOk();
        $response->assertViewHas('metrics', function (array $metrics) {
            return (float) $metrics['gross_collection'] === 1000.0
                && (float) $metrics['expenses'] === 250.0
                && (float) $metrics['closing_balance'] === 750.0
                && (int) $metrics['students'] === 1;
        });
    }

    public function test_cashbook_branch_filter_scopes_category_totals(): void
    {
        $admin = User::query()->where('role', 'super_admin')->firstOrFail();
        $branchA = Branch::query()->where('status', true)->firstOrFail();
        $branchB = Branch::create(['name' => 'Branch C', 'code' => 'BR-C', 'status' => true]);

        Expense::create([
            'branch_id' => $branchA->id,
            'expense_date' => today(),
            'category' => 'Electricity',
            'amount' => 300,
            'payment_mode' => 'cash',
            'created_by' => $admin->id,
        ]);
        Expense::create([
            'branch_id' => $branchA->id,
            'expense_date' => today(),
            'category' => 'Internet',
            'amount' => 100,
            'payment_mode' => 'cash',
            'created_by' => $admin->id,
        ]);
        Expense::create([
            'branch_id' => $branchB->id,
            'expense_date' => today(),
            'category' => 'Electricity',
            'amount' => 900,
            'payment_mode' => 'cash',
            'created_by' => $admin->id,
        ]);

        $response = $this->actingAs($admin)->get(route('admin.expenses.index', [
            'from' => today()->toDateString(),
            'to' => today()->toDateString(),
            'branch_id' => $branchA->id,
        ]));

        $response->assertOk();
        $response->assertViewHas('totalExpenses', 400.0);
        $response->assertViewHas('categoryTotals', function ($totals) {
            $values = $totals->pluck('total', 'category')->map(fn ($value) => (float) $value);
            return $values->get('Electricity') === 300.0
                && $values->get('Internet') === 100.0
                && $values->sum() === 400.0;
        });
    }

    private function studentWithMembership(Branch $branch, string $code): array
    {
        $slot = StudySlot::create([
            'branch_id' => $branch->id,
            'name' => $code.' Slot',
            'duration_hours' => 6,
            'start_time' => '08:00:00',
            'end_time' => '14:00:00',
            'status' => true,
        ]);

        $plan = FeePlan::create([
            'branch_id' => $branch->id,
            'study_slot_id' => $slot->id,
            'name' => $code.' Plan',
            'monthly_fee' => 3000,
            'validity_days' => 30,
            'status' => true,
        ]);

        $student = Student::create([
            'branch_id' => $branch->id,
            'student_code' => $code,
            'name' => $code,
            'mobile' => '9'.str_pad((string) random_int(1, 999999999), 9, '0', STR_PAD_LEFT),
            'joining_date' => today(),
            'status' => 'active',
        ]);

        $membership = StudentMembership::create([
            'student_id' => $student->id,
            'fee_plan_id' => $plan->id,
            'study_slot_id' => $slot->id,
            'start_date' => today(),
            'expiry_date' => today()->addDays(29),
            'base_fee' => 3000,
            'discount' => 0,
            'final_fee' => 3000,
            'status' => 'active',
        ]);

        return [$student, $membership];
    }
}
