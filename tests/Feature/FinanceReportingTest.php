<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\FeePlan;
use App\Models\Payment;
use App\Models\PaymentAdjustment;
use App\Models\Student;
use App\Models\StudentMembership;
use App\Models\StudySlot;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FinanceReportingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_reports_show_net_collection_and_reopened_due_after_adjustment(): void
    {
        [$admin, $student, $membership] = $this->financeFixture();

        $payment = Payment::create([
            'student_id' => $student->id,
            'student_membership_id' => $membership->id,
            'receipt_no' => 'CNL-2026-REPORTTEST',
            'amount' => 1000,
            'discount' => 0,
            'late_fee' => 0,
            'payment_date' => today(),
            'payment_mode' => 'cash',
            'payment_status' => 'paid',
            'received_by' => $admin->id,
        ]);

        PaymentAdjustment::create([
            'payment_id' => $payment->id,
            'type' => 'refund',
            'amount' => 250,
            'reason' => 'Test refund',
            'adjustment_date' => today(),
            'created_by' => $admin->id,
        ]);

        $response = $this->actingAs($admin)->get(route('admin.reports.index'));

        $response->assertOk();
        $response->assertSee('Net Fee Collection');
        $response->assertSee('750.00');
        $response->assertSee('250.00');
        $response->assertSee('Daily Net Collection');
    }

    public function test_receipt_shows_adjustment_history_and_original_payment_value(): void
    {
        [$admin, $student, $membership] = $this->financeFixture();

        $payment = Payment::create([
            'student_id' => $student->id,
            'student_membership_id' => $membership->id,
            'receipt_no' => 'CNL-2026-HISTORY01',
            'amount' => 1000,
            'discount' => 0,
            'late_fee' => 0,
            'payment_date' => today(),
            'payment_mode' => 'upi',
            'transaction_ref' => 'TX-HISTORY-01',
            'payment_status' => 'paid',
            'received_by' => $admin->id,
        ]);

        PaymentAdjustment::create([
            'payment_id' => $payment->id,
            'type' => 'correction',
            'amount' => 100,
            'reason' => 'Receipt correction',
            'adjustment_date' => today(),
            'created_by' => $admin->id,
        ]);

        $this->actingAs($admin)
            ->get(route('admin.payments.receipt', $payment))
            ->assertOk()
            ->assertSee('Original Payment')
            ->assertSee('1,000.00')
            ->assertSee('Adjustment History')
            ->assertSee('Receipt correction')
            ->assertSee('900.00');
    }

    public function test_student_cannot_create_payment_adjustment(): void
    {
        [$admin, $student, $membership, $studentUser] = $this->financeFixture(withStudentUser: true);

        $payment = Payment::create([
            'student_id' => $student->id,
            'student_membership_id' => $membership->id,
            'receipt_no' => 'CNL-2026-FORBID01',
            'amount' => 500,
            'discount' => 0,
            'late_fee' => 0,
            'payment_date' => today(),
            'payment_mode' => 'cash',
            'payment_status' => 'partial',
            'received_by' => $admin->id,
        ]);

        $this->actingAs($studentUser)
            ->post(route('admin.payments.adjust', $payment), [
                'type' => 'refund',
                'amount' => 100,
                'reason' => 'Unauthorized attempt',
            ])
            ->assertForbidden();

        $this->assertDatabaseMissing('payment_adjustments', [
            'payment_id' => $payment->id,
            'reason' => 'Unauthorized attempt',
        ]);
    }

    private function financeFixture(bool $withStudentUser = false): array
    {
        $branch = Branch::query()->where('status', true)->firstOrFail();
        $slot = StudySlot::query()->where('branch_id', $branch->id)->where('status', true)->firstOrFail();
        $plan = FeePlan::query()->where('branch_id', $branch->id)->where('status', true)->firstOrFail();

        $admin = User::query()->where('role', 'super_admin')->firstOrFail();
        $studentUser = null;

        if ($withStudentUser) {
            $studentUser = User::create([
                'name' => 'Finance Student',
                'email' => 'finance-student@example.com',
                'password' => 'password123',
                'role' => 'student',
                'status' => true,
            ]);
        }

        $student = Student::create([
            'branch_id' => $branch->id,
            'user_id' => $studentUser?->id,
            'student_code' => 'CNL-FIN-'.uniqid(),
            'qr_token' => (string) \Illuminate\Support\Str::uuid(),
            'name' => 'Finance Student',
            'mobile' => '9000000001',
            'joining_date' => today(),
            'status' => 'active',
        ]);

        $membership = StudentMembership::create([
            'student_id' => $student->id,
            'fee_plan_id' => $plan->id,
            'study_slot_id' => $slot->id,
            'start_date' => today(),
            'expiry_date' => today()->addDays(30),
            'base_fee' => 1000,
            'discount' => 0,
            'final_fee' => 1000,
            'status' => 'active',
        ]);

        return [$admin, $student, $membership, $studentUser];
    }
}
