<?php

namespace Tests\Feature;

use App\Models\Book;
use App\Models\BookCopy;
use App\Models\BookIssue;
use App\Models\Branch;
use App\Models\FeePlan;
use App\Models\LibraryChargePayment;
use App\Models\Payment;
use App\Models\Student;
use App\Models\StudentMembership;
use App\Models\StudySlot;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class FinancialReportingIntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_reports_include_library_recoveries_without_merging_them_into_membership_income(): void
    {
        $admin = User::query()->where('role', 'super_admin')->firstOrFail();
        $branch = Branch::query()->where('status', true)->firstOrFail();
        $feePlan = FeePlan::query()->where('branch_id', $branch->id)->firstOrFail();
        $studySlot = StudySlot::query()->where('branch_id', $branch->id)->first();

        $student = Student::create([
            'branch_id' => $branch->id,
            'student_code' => 'CNL-FIN-INT',
            'qr_token' => (string) Str::uuid(),
            'name' => 'Finance Integration Student',
            'mobile' => '9000000091',
            'joining_date' => today(),
            'status' => 'active',
        ]);

        $membership = StudentMembership::create([
            'student_id' => $student->id,
            'fee_plan_id' => $feePlan->id,
            'study_slot_id' => $studySlot?->id,
            'start_date' => today(),
            'expiry_date' => today()->addDays(30),
            'base_fee' => 1000,
            'discount' => 0,
            'final_fee' => 1000,
            'status' => 'active',
        ]);

        Payment::create([
            'student_id' => $student->id,
            'student_membership_id' => $membership->id,
            'receipt_no' => 'TEST-2026-000001',
            'amount' => 500,
            'payment_date' => today(),
            'payment_mode' => 'cash',
            'payment_status' => 'partial',
            'received_by' => $admin->id,
        ]);

        $book = Book::factory()->create();
        $copy = BookCopy::create([
            'book_id' => $book->id,
            'branch_id' => $branch->id,
            'accession_no' => 'FIN-INT-001',
            'condition' => 'good',
            'status' => 'issued',
        ]);

        $issue = BookIssue::create([
            'student_id' => $student->id,
            'book_copy_id' => $copy->id,
            'issued_at' => today()->subDays(10),
            'due_at' => today()->subDay(),
            'fine_amount' => 50,
            'status' => 'returned',
            'returned_at' => today(),
        ]);

        LibraryChargePayment::create([
            'book_issue_id' => $issue->id,
            'charge_type' => 'fine',
            'amount' => 50,
            'payment_date' => today(),
            'payment_mode' => 'cash',
            'received_by' => $admin->id,
        ]);

        $response = $this->actingAs($admin)->get(route('admin.reports.index', [
            'from' => today()->toDateString(),
            'to' => today()->toDateString(),
            'branch_id' => $branch->id,
        ]));

        $response->assertOk();
        $response->assertViewHas('metrics', function (array $metrics) {
            return (float) $metrics['membership_income'] === 500.0
                && (float) $metrics['library_income'] === 50.0
                && (float) $metrics['total_income'] === 550.0;
        });
    }

    public function test_dashboard_cash_position_includes_library_recoveries(): void
    {
        $admin = User::query()->where('role', 'super_admin')->firstOrFail();
        $branch = Branch::query()->where('status', true)->firstOrFail();

        $student = Student::create([
            'branch_id' => $branch->id,
            'student_code' => 'CNL-FIN-DASH',
            'qr_token' => (string) Str::uuid(),
            'name' => 'Dashboard Finance Student',
            'mobile' => '9000000092',
            'joining_date' => today(),
            'status' => 'active',
        ]);

        $book = Book::factory()->create();
        $copy = BookCopy::create([
            'book_id' => $book->id,
            'branch_id' => $branch->id,
            'accession_no' => 'FIN-DASH-001',
            'condition' => 'good',
            'status' => 'lost',
        ]);

        $issue = BookIssue::create([
            'student_id' => $student->id,
            'book_copy_id' => $copy->id,
            'issued_at' => today()->subDays(5),
            'due_at' => today(),
            'loss_charge' => 200,
            'status' => 'lost',
        ]);

        LibraryChargePayment::create([
            'book_issue_id' => $issue->id,
            'charge_type' => 'loss',
            'amount' => 200,
            'payment_date' => today(),
            'payment_mode' => 'cash',
            'received_by' => $admin->id,
        ]);

        $response = $this->actingAs($admin)->get(route('admin.dashboard'));

        $response->assertOk();
        $response->assertViewHas('data', function (array $data) {
            return (float) $data['today_library_income'] >= 200.0
                && (float) $data['today_total_income'] >= 200.0
                && (float) $data['today_cash_position'] === (float) $data['today_total_income'] - (float) $data['today_expenses'];
        });
    }
}
