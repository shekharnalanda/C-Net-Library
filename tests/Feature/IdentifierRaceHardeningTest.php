<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\FeePlan;
use App\Models\Payment;
use App\Models\Student;
use App\Models\StudentMembership;
use App\Models\StudySlot;
use App\Models\User;
use App\Services\ApplicationNumberService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class IdentifierRaceHardeningTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_application_numbers_are_collision_resistant_and_have_expected_format(): void
    {
        $service = app(ApplicationNumberService::class);

        $first = $service->generate();
        $second = $service->generate();

        $this->assertNotSame($first, $second);
        $this->assertMatchesRegularExpression('/^CNL-ADM-\d{4}-[A-Z0-9]{8}$/', $first);
        $this->assertMatchesRegularExpression('/^CNL-ADM-\d{4}-[A-Z0-9]{8}$/', $second);
    }

    public function test_payment_transaction_reference_is_unique_at_database_level(): void
    {
        $branch = Branch::query()->where('status', true)->firstOrFail();
        $admin = User::query()->where('role', 'super_admin')->firstOrFail();
        $student = Student::create([
            'branch_id' => $branch->id,
            'student_code' => 'TXN-'.Str::upper(Str::random(8)),
            'qr_token' => (string) Str::uuid(),
            'name' => 'Transaction Student',
            'mobile' => '9111111111',
            'joining_date' => today(),
            'status' => 'active',
        ]);
        $slot = StudySlot::query()->where('branch_id', $branch->id)->where('status', true)->firstOrFail();
        $plan = FeePlan::query()->where('branch_id', $branch->id)->where('status', true)->firstOrFail();
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

        $attributes = [
            'student_id' => $student->id,
            'student_membership_id' => $membership->id,
            'amount' => 100,
            'discount' => 0,
            'late_fee' => 0,
            'payment_date' => today(),
            'payment_mode' => 'upi',
            'transaction_ref' => 'UPI-UNIQUE-001',
            'payment_status' => 'partial',
            'received_by' => $admin->id,
        ];

        Payment::create($attributes + ['receipt_no' => 'R-'.Str::upper(Str::random(10))]);

        $this->expectException(\Illuminate\Database\QueryException::class);
        Payment::create($attributes + ['receipt_no' => 'R-'.Str::upper(Str::random(10))]);
    }
}
