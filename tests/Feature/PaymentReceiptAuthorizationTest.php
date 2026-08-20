<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\FeePlan;
use App\Models\Payment;
use App\Models\Role;
use App\Models\Student;
use App\Models\StudentMembership;
use App\Models\StudySlot;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class PaymentReceiptAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_branch_admin_cannot_view_payment_receipt_from_another_branch(): void
    {
        $ownBranch = Branch::factory()->create(['status' => true]);
        $otherBranch = Branch::factory()->create(['status' => true]);

        $admin = User::factory()->create([
            'branch_id' => $ownBranch->id,
            'role' => 'admin',
            'status' => true,
        ]);
        $admin->roles()->syncWithoutDetaching([
            Role::query()->where('slug', 'branch-admin')->firstOrFail()->id,
        ]);

        $slot = StudySlot::factory()->create([
            'branch_id' => $otherBranch->id,
            'status' => true,
        ]);
        $plan = FeePlan::create([
            'branch_id' => $otherBranch->id,
            'study_slot_id' => $slot->id,
            'name' => 'Other Branch Plan',
            'monthly_fee' => 1000,
            'validity_days' => 30,
            'status' => true,
        ]);
        $student = Student::create([
            'branch_id' => $otherBranch->id,
            'student_code' => 'RCT-'.Str::upper(Str::random(8)),
            'qr_token' => (string) Str::uuid(),
            'name' => 'Other Branch Student',
            'mobile' => '9000099001',
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
            'receipt_no' => 'RCT-'.Str::upper(Str::random(10)),
            'amount' => 1000,
            'discount' => 0,
            'late_fee' => 0,
            'payment_date' => today(),
            'payment_mode' => 'cash',
            'payment_status' => 'paid',
            'received_by' => $admin->id,
        ]);

        $this->actingAs($admin)
            ->get(route('admin.payments.receipt', $payment))
            ->assertForbidden();
    }
}
