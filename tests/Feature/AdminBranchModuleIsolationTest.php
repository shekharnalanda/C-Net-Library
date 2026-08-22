<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\Book;
use App\Models\BookCategory;
use App\Models\BookCopy;
use App\Models\Branch;
use App\Models\DigitalResource;
use App\Models\Expense;
use App\Models\Job;
use App\Models\Role;
use App\Models\Staff;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminBranchModuleIsolationTest extends TestCase
{
    use RefreshDatabase;

    public function test_branch_admin_only_sees_assigned_branch_operational_data(): void
    {
        $branchA = Branch::factory()->create(['name' => 'Branch A', 'status' => true]);
        $branchB = Branch::factory()->create(['name' => 'Branch B', 'status' => true]);

        $admin = User::factory()->create([
            'role' => 'admin',
            'branch_id' => $branchA->id,
            'status' => true,
        ]);
        $role = Role::query()->create(['name' => 'Branch Admin', 'slug' => 'branch-admin', 'is_system' => true]);
        $admin->roles()->attach($role);

        $studentA = Student::factory()->create(['branch_id' => $branchA->id, 'name' => 'Student A', 'status' => 'active']);
        $studentB = Student::factory()->create(['branch_id' => $branchB->id, 'name' => 'Student B', 'status' => 'active']);

        Attendance::factory()->create(['student_id' => $studentA->id, 'branch_id' => $branchA->id, 'attendance_date' => today()]);
        Attendance::factory()->create(['student_id' => $studentB->id, 'branch_id' => $branchB->id, 'attendance_date' => today()]);

        $category = BookCategory::factory()->create();
        $book = Book::factory()->create(['book_category_id' => $category->id]);
        BookCopy::factory()->create(['book_id' => $book->id, 'branch_id' => $branchA->id, 'accession_no' => 'A-COPY']);
        BookCopy::factory()->create(['book_id' => $book->id, 'branch_id' => $branchB->id, 'accession_no' => 'B-COPY']);

        DigitalResource::factory()->create(['branch_id' => $branchA->id, 'title' => 'Resource A']);
        DigitalResource::factory()->create(['branch_id' => $branchB->id, 'title' => 'Resource B']);

        Job::factory()->create(['branch_id' => $branchA->id, 'title' => 'Job A']);
        Job::factory()->create(['branch_id' => $branchB->id, 'title' => 'Job B']);

        Staff::factory()->create(['branch_id' => $branchA->id, 'name' => 'Staff A']);
        Staff::factory()->create(['branch_id' => $branchB->id, 'name' => 'Staff B']);

        Expense::factory()->create(['branch_id' => $branchA->id, 'category' => 'Rent A', 'amount' => 100, 'expense_date' => today()]);
        Expense::factory()->create(['branch_id' => $branchB->id, 'category' => 'Rent B', 'amount' => 200, 'expense_date' => today()]);

        $this->actingAs($admin)->get(route('admin.attendance.index'))
            ->assertOk()->assertSee('Student A')->assertDontSee('Student B');

        $this->actingAs($admin)->get(route('admin.library.index'))
            ->assertOk()->assertSee('A-COPY')->assertDontSee('B-COPY');

        $this->actingAs($admin)->get(route('admin.digital-resources.index'))
            ->assertOk()->assertSee('Resource A')->assertDontSee('Resource B');

        $this->actingAs($admin)->get(route('admin.jobs.index'))
            ->assertOk()->assertSee('Job A')->assertDontSee('Job B');

        $this->actingAs($admin)->get(route('admin.staff.index'))
            ->assertOk()->assertSee('Staff A')->assertDontSee('Staff B');

        $this->actingAs($admin)->get(route('admin.expenses.index'))
            ->assertOk()->assertSee('Rent A')->assertDontSee('Rent B');
    }

    public function test_branch_admin_cannot_issue_cross_branch_book_copy(): void
    {
        $branchA = Branch::factory()->create(['status' => true]);
        $branchB = Branch::factory()->create(['status' => true]);
        $admin = User::factory()->create(['role' => 'admin', 'branch_id' => $branchA->id, 'status' => true]);
        $student = Student::factory()->create(['branch_id' => $branchA->id, 'status' => 'active']);
        $category = BookCategory::factory()->create();
        $book = Book::factory()->create(['book_category_id' => $category->id]);
        $copy = BookCopy::factory()->create(['book_id' => $book->id, 'branch_id' => $branchB->id]);

        $this->actingAs($admin)
            ->post(route('admin.library.issue'), [
                'student_id' => $student->id,
                'book_copy_id' => $copy->id,
                'issue_days' => 7,
            ])
            ->assertForbidden();
    }
}
