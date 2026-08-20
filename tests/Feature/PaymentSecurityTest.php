<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\FeePlan;
use App\Models\Payment;
use App\Models\Student;
use App\Models\StudentMembership;
use App\Models\StudySlot;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class PaymentSecurityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_duplicate_transaction_reference_is_rejected(): void
    {
        [$admin, $student, $membership] = $this->fixture();

        Payment::create([
            'student_id' => $student->id,
            'student_membership_id' => $membership->id,
            'receipt_no' => 'CNL-TEST-000001',
            'amount' => 100,
            'discount' => 0,
            'late_fee' => 0,
            'payment_date' => today(),
            'payment_mode' => 'upi',
            'transaction_ref' => 'UTR-12345',
            'payment_status' => 'partial',
            'received_by' => $admin->id,
        ]);

        $this->actingAs($admin)
            ->post(route('admin.students.payments.store', $student), [
                'student_membership_id' => $membership->id,
                'amount' => 100,
                'payment_mode' => 'upi',
                'transaction_ref' => 'UTR-12345',
            ])
            ->assertSessionHasErrors('transaction_ref');

        $this->assertSame(1, Payment::query()->where('transaction_ref', 'UTR-12345')->count());
    }

    public function test_payment_cannot_exceed_current_due(): void
    {
        [$admin, $student, $membership] = $this->fixture(finalFee: 500);

        $this->actingAs($admin)
            ->post(route('admin.students.payments.store', $student), [
                'student_membership_id' => $membership->id,
                'amount' => 501,
                'payment_mode' => 'cash',
            ])
            ->assertSessionHasErrors('amount');

        $this->assertDatabaseCount('payments', 0);
    }

    public function test_second_payment_is_rejected_when_membership_is_fully_paid(): void
    {
        [$admin, $student, $membership] = $this->fixture(finalFee: 500);

        Payment::create([
            'student_id' => $student->id,
            'student_membership_id' => $membership->id,
            'receipt_no' => 'CNL-TEST-000002',
            'amount' => 500,
            'discount' => 0,
            'late_fee' => 0,
            'payment_date' => today(),
            'payment_mode' => 'cash',
            'payment_status' => 'paid',
            'received_by' => $admin->id,
        ]);

        $this->actingAs($admin)
            ->post(route('admin.students.payments.store', $student), [
                'student_membership_id' => $membership->id,
                'amount' => 1,
                'payment_mode' => 'cash',
            ])
            ->assertSessionHasErrors('amount');

        $this->assertSame(1, Payment::query()->count());
    }

    public function test_receipt_response_is_private_and_not_indexable(): void
    {
        [$admin, $student, $membership] = $this->fixture();

        $payment = Payment::create([
            'student_id' => $student->id,
            'student_membership_id' => $membership->id,
            'receipt_no' => 'CNL-TEST-000003',
            'amount' => 100,
            'discount' => 0,
            'late_fee' => 0,
            'payment_date' => today(),
            'payment_mode' => 'cash',
            'payment_status' => 'partial',
            'received_by' => $admin->id,
        ]);

        $response = $this->actingAs($admin)->get(route('admin.payments.receipt', $payment));

        $response->assertOk();
        $response->assertHeader('X-Robots-Tag', 'noindex, nofollow, noarchive');
        $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
    }

    public function test_student_cannot_access_admin_receipt_route(): void
    {
        [$admin, $student, $membership] = $this->fixture();

        $payment = Payment::create([
            'student_id' => $student->id,
            'student_membership_id' => $membership->id,
            'receipt_no' => 'CNL-TEST-000004',
            'amount' => 100,
            'discount' => 0,
            'late_fee' => 0,
            'payment_date' => today(),
            'payment_mode' => 'cash',
            'payment_status' => 'partial',
            'received_by' => $admin->id,
        ]);

        $studentUser = User::create([
            'name' => 'Receipt Student',
            'email' => 'receipt-student@example.com',
            'password' => 'password123',
            'role' => 'student',
            'status' => true,
        ]);
        $student->update(['user_id' => $studentUser->id]);

        $this->actingAs($studentUser)
            ->get(route('admin.payments.receipt', $payment))
            ->assertForbidden();
    }

    private function fixture(float $finalFee = 1000): array
    {
        $branch = Branch::query()->where('status', true)->firstOrFail();
        $slot = StudySlot::query()->where('branch_id', $branch->id)->where('status', true)->firstOrFail();
        $plan = FeePlan::query()->where('branch_id', $branch->id)->where('status', true)->firstOrFail();
        $admin = User::query()->where('role', 'super_admin')->firstOrFail();

        $student = Student::create([
            'branch_id' => $branch->id,
            'student_code' => 'CNL-PAY-'.Str::upper(Str::random(8)),
            'qr_token' => (string) Str::uuid(),
            'name' => 'Payment Test Student',
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
            'base_fee' => $finalFee,
            'discount' => 0,
            'final_fee' => $finalFee,
            'status' => 'active',
        ]);

        return [$admin, $student, $membership];
    }
}
