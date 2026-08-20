<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\FeePlan;
use App\Models\Payment;
use App\Models\PaymentAdjustment;
use App\Models\Student;
use App\Models\StudentMembership;
use App\Models\StudySlot;
use App\Services\ReceiptService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class ReceiptIntegrityTest extends TestCase
{
    use RefreshDatabase;

    public function test_receipt_numbers_are_sequential_and_globally_unique_across_branches(): void
    {
        $branchA = Branch::factory()->create(['status' => true]);
        $branchB = Branch::factory()->create(['status' => true]);

        $service = app(ReceiptService::class);

        $firstA = $service->generate('CNL', $branchA->id);
        $secondA = $service->generate('CNL', $branchA->id);
        $firstB = $service->generate('CNL', $branchB->id);

        $year = now()->format('Y');
        $seriesA = 'B'.str_pad((string) $branchA->id, 6, '0', STR_PAD_LEFT);
        $seriesB = 'B'.str_pad((string) $branchB->id, 6, '0', STR_PAD_LEFT);

        $this->assertSame("CNL-{$seriesA}-{$year}-000001", $firstA);
        $this->assertSame("CNL-{$seriesA}-{$year}-000002", $secondA);
        $this->assertSame("CNL-{$seriesB}-{$year}-000001", $firstB);
        $this->assertNotSame($firstA, $firstB);
    }

    public function test_receipt_prefix_is_normalized_and_global_series_is_explicit(): void
    {
        $receipt = app(ReceiptService::class)->generate(' C Net / Library ', null);
        $year = now()->format('Y');

        $this->assertSame("C-NET-LIBRARY-GLOBAL-{$year}-000001", $receipt);
    }

    public function test_receipt_snapshot_is_not_changed_by_later_adjustment(): void
    {
        $branch = Branch::factory()->create(['status' => true]);
        $slot = StudySlot::factory()->create([
            'branch_id' => $branch->id,
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
        $student = Student::create([
            'branch_id' => $branch->id,
            'student_code' => 'RCT-'.Str::upper(Str::random(8)),
            'qr_token' => (string) Str::uuid(),
            'name' => 'Receipt Student',
            'mobile' => '9000000099',
            'joining_date' => today(),
            'status' => 'active',
        ]);
        $membership = StudentMembership::create([
            'student_id' => $student->id,
            'fee_plan_id' => $plan->id,
            'study_slot_id' => $slot->id,
            'start_date' => today(),
            'expiry_date' => today()->addDays(29),
            'base_fee' => 1000,
            'discount' => 0,
            'final_fee' => 1000,
            'status' => 'active',
        ]);

        $payment = Payment::create([
            'student_id' => $student->id,
            'student_membership_id' => $membership->id,
            'receipt_no' => 'TEST-SNAPSHOT-'.Str::upper(Str::random(8)),
            'amount' => 600,
            'receipt_previous_paid' => 0,
            'receipt_balance_due' => 400,
            'receipt_membership_fee' => 1000,
            'payment_date' => today(),
            'payment_mode' => 'cash',
            'payment_status' => 'partial',
        ]);

        PaymentAdjustment::create([
            'payment_id' => $payment->id,
            'type' => 'refund',
            'amount' => 200,
            'reason' => 'Test refund',
        ]);

        $payment->refresh();

        $this->assertSame('0.00', $payment->receipt_previous_paid);
        $this->assertSame('400.00', $payment->receipt_balance_due);
        $this->assertSame('1000.00', $payment->receipt_membership_fee);
        $this->assertSame(200.0, (float) $payment->adjustments()->sum('amount'));
    }
}
