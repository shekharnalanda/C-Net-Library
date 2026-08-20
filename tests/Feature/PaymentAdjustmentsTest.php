<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Payment;
use App\Models\PaymentAdjustment;
use App\Models\Student;
use App\Models\StudentMembership;
use App\Models\User;
use App\Services\ReceiptService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class PaymentAdjustmentsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_adjustment_is_append_only_and_does_not_mutate_original_payment(): void
    {
        [$admin, $payment] = $this->paymentFixture(1000);

        $this->actingAs($admin)
            ->post(route('admin.payments.adjustments.store', $payment), [
                'type' => 'refund',
                'amount' => 250,
                'reason' => 'Approved refund',
            ])
            ->assertRedirect();

        $payment->refresh();
        $this->assertSame('1000.00', $payment->amount);
        $this->assertSame('paid', $payment->payment_status);
        $this->assertDatabaseHas('payment_adjustments', [
            'payment_id' => $payment->id,
            'type' => 'refund',
            'amount' => 250,
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'payment.adjustment.created',
            'auditable_type' => PaymentAdjustment::class,
        ]);
    }

    public function test_adjustment_cannot_exceed_remaining_payment_amount(): void
    {
        [$admin, $payment] = $this->paymentFixture(500);

        PaymentAdjustment::create([
            'payment_id' => $payment->id,
            'type' => 'refund',
            'amount' => 400,
            'reason' => 'First refund',
            'created_by' => $admin->id,
        ]);

        $this->actingAs($admin)
            ->post(route('admin.payments.adjustments.store', $payment), [
                'type' => 'refund',
                'amount' => 101,
                'reason' => 'Too much',
            ])
            ->assertSessionHasErrors('amount');

        $this->assertSame(1, PaymentAdjustment::query()->where('payment_id', $payment->id)->count());
    }

    public function test_adjustment_reopens_membership_due_for_new_payment(): void
    {
        [$admin, $payment, $student, $membership] = $this->paymentFixture(1000, 1000);

        PaymentAdjustment::create([
            'payment_id' => $payment->id,
            'type' => 'refund',
            'amount' => 200,
            'reason' => 'Partial refund',
            'created_by' => $admin->id,
        ]);

        $this->actingAs($admin)
            ->post(route('admin.students.payments.store', $student), [
                'student_membership_id' => $membership->id,
                'amount' => 200,
                'payment_mode' => 'cash',
            ])
            ->assertRedirect();

        $this->assertSame(1200.0, (float) Payment::query()
            ->where('student_membership_id', $membership->id)
            ->whereIn('payment_status', ['paid', 'partial'])
            ->sum('amount'));
    }

    public function test_receipt_service_generates_distinct_collision_resistant_numbers(): void
    {
        $branch = Branch::query()->where('status', true)->firstOrFail();
        $service = app(ReceiptService::class);

        $first = $service->generate(branchId: $branch->id);
        $second = $service->generate(branchId: $branch->id);

        $this->assertNotSame($first, $second);
        $this->assertMatchesRegularExpression('/^[A-Z0-9_-]+-\d{4}-[A-Z0-9]{10}$/', $first);
    }

    private function paymentFixture(float $amount, float $finalFee = 1000): array
    {
        $branch = Branch::query()->where('status', true)->firstOrFail();
        $admin = User::query()->where('role', 'super_admin')->firstOrFail();

        $student = Student::create([
            'branch_id' => $branch->id,
            'student_code' => 'PAY-'.Str::upper(Str::random(8)),
            'qr_token' => (string) Str::uuid(),
            'name' => 'Finance Student',
            'mobile' => '9000000000',
            'joining_date' => today(),
            'status' => 'active',
        ]);

        $membership = StudentMembership::create([
            'student_id' => $student->id,
            'start_date' => today(),
            'expiry_date' => today()->addMonth(),
            'base_fee' => $finalFee,
            'discount' => 0,
            'final_fee' => $finalFee,
            'status' => 'active',
        ]);

        $payment = Payment::create([
            'student_id' => $student->id,
            'student_membership_id' => $membership->id,
            'receipt_no' => 'TEST-'.Str::upper(Str::random(12)),
            'amount' => $amount,
            'discount' => 0,
            'late_fee' => 0,
            'payment_date' => today(),
            'payment_mode' => 'cash',
            'payment_status' => $amount >= $finalFee ? 'paid' : 'partial',
            'received_by' => $admin->id,
        ]);

        return [$admin, $payment, $student, $membership];
    }
}
