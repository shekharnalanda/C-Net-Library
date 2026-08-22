<?php

namespace Tests\Feature;

use App\Models\Book;
use App\Models\BookCopy;
use App\Models\BookIssue;
use App\Models\Branch;
use App\Models\FeePlan;
use App\Models\Student;
use App\Models\StudentMembership;
use App\Models\StudySlot;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class ReportLifecycleEdgeCaseTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_reports_ignore_stale_expired_membership_and_lost_issue_as_active(): void
    {
        $admin = User::query()->where('role', 'super_admin')->firstOrFail();
        $branch = Branch::query()->where('status', true)->firstOrFail();
        $slot = StudySlot::query()->where('branch_id', $branch->id)->where('status', true)->firstOrFail();
        $plan = FeePlan::query()->where('branch_id', $branch->id)->where('status', true)->firstOrFail();

        $student = Student::create([
            'branch_id' => $branch->id,
            'student_code' => 'REP-'.Str::upper(Str::random(8)),
            'qr_token' => (string) Str::uuid(),
            'name' => 'Report Edge Student',
            'mobile' => '9000099551',
            'joining_date' => today()->subMonth(),
            'status' => 'active',
        ]);

        StudentMembership::create([
            'student_id' => $student->id,
            'fee_plan_id' => $plan->id,
            'study_slot_id' => $slot->id,
            'start_date' => today()->subDays(30),
            'expiry_date' => today()->subDay(),
            'base_fee' => 1000,
            'discount' => 0,
            'final_fee' => 1000,
            'status' => 'active',
        ]);

        $book = Book::factory()->create();
        $copy = BookCopy::create([
            'book_id' => $book->id,
            'branch_id' => $branch->id,
            'accession_no' => 'REP-'.Str::upper(Str::random(8)),
            'condition' => 'good',
            'status' => 'lost',
        ]);
        BookIssue::create([
            'student_id' => $student->id,
            'book_copy_id' => $copy->id,
            'issued_at' => today()->subDays(5),
            'due_at' => today()->subDay(),
            'loss_charge' => 500,
            'status' => 'lost',
        ]);

        $response = $this->actingAs($admin)->get(route('admin.reports.index', [
            'branch_id' => $branch->id,
        ]));

        $response->assertOk();
        $response->assertViewHas('metrics', function (array $metrics) {
            return (int) $metrics['active_memberships'] === 0
                && (float) $metrics['due'] === 0.0
                && (int) $metrics['books_issued'] === 0;
        });
    }
}
