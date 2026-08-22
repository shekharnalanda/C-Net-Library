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
use Illuminate\Support\Str;
use Tests\TestCase;

class StudentPaymentSummaryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_student_profile_uses_net_paid_after_adjustments(): void
    {
        $branch = Branch::query()->where('status', true)->firstOrFail();
        $admin = User::query()->where('role', 'super_admin')->firstOrFail();

        $student = Student::create([
            'branch_id' => $branch->id,
            'student_code' => 'SUM-'.Str::upper(Str::random(8)),
            'qr_token' => (string) Str::uuid(),
            'name' => 'Summary Student',
            'mobile' => '9000000099',
            'joining_date' => today(),
            'status' => 'active',
        ]);

        $slot = StudySlot::factory()->create(['branch_id' => $branch->id]);
        $plan = FeePlan::factory()->create([
            'branch_id' => $branch->id,
            'study_slot_id' => $slot->id,
        ]);

        $membership = StudentMembership::create([
            'student_id' => $student->id,
            'fee_plan_id' => $plan->id,
            'study_slot_id' => $slot->id,
            'start_date' => today(),
            'expiry_date' => today()->addMonth(),
            'base_fee' => 1000,
            'discount' => 0,
            'final_fee' => 1000,
            'status' => 'active',
        ]);

        $payment = Payment::create([
            'student_id' => $student->id,
            'student_membership_id' => $membership->id,
            'receipt_no' => 'SUM-'.Str::upper(Str::random(12)),
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
            'reason' => 'Partial refund',
            'created_by' => $admin->id,
        ]);

        $response = $this->actingAs($admin)->get(route('admin.students.show', $student));

        $response->assertOk();
        $response->assertSee('Paid (Net)');
        $response->assertSee('₹750.00');
        $response->assertSee('Refunds / Adjustments');
        $response->assertSee('₹250.00');
        $response->assertSee('Due');
    }
}
