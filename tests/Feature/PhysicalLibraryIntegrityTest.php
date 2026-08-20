<?php

namespace Tests\Feature;

use App\Models\Book;
use App\Models\BookCategory;
use App\Models\BookCopy;
use App\Models\BookIssue;
use App\Models\Branch;
use App\Models\Student;
use App\Services\LibraryCirculationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class PhysicalLibraryIntegrityTest extends TestCase
{
    use RefreshDatabase;

    public function test_inactive_student_cannot_borrow_book(): void
    {
        [$student, $copy] = $this->studentAndCopy('inactive');

        $this->expectException(ValidationException::class);

        app(LibraryCirculationService::class)->issue($student, $copy, 14, null);
    }

    public function test_damaged_return_keeps_copy_out_of_circulation(): void
    {
        [$student, $copy] = $this->studentAndCopy();
        $issue = app(LibraryCirculationService::class)->issue($student, $copy, 14, null);

        $returned = app(LibraryCirculationService::class)->return($issue, 5, null, 'damaged');

        $this->assertSame('returned', $returned->status);
        $this->assertSame('damaged', $returned->return_condition);
        $this->assertSame('damaged', $copy->fresh()->status);
        $this->assertSame('damaged', $copy->fresh()->condition);
    }

    public function test_lost_issue_marks_copy_lost_and_cannot_be_returned_normally(): void
    {
        [$student, $copy] = $this->studentAndCopy();
        $issue = app(LibraryCirculationService::class)->issue($student, $copy, 14, null);

        $lost = app(LibraryCirculationService::class)->markLost($issue, 750, null, 'Student reported the book missing.');

        $this->assertSame('lost', $lost->status);
        $this->assertSame('750.00', $lost->loss_charge);
        $this->assertSame('lost', $copy->fresh()->status);

        $this->expectException(ValidationException::class);
        app(LibraryCirculationService::class)->return($lost, 5, null, 'good');
    }

    public function test_overdue_return_calculates_fine_and_restores_good_copy(): void
    {
        [$student, $copy] = $this->studentAndCopy();

        $issue = BookIssue::create([
            'student_id' => $student->id,
            'book_copy_id' => $copy->id,
            'issued_at' => today()->subDays(10),
            'due_at' => today()->subDays(3),
            'status' => 'issued',
        ]);
        $copy->update(['status' => 'issued']);

        $returned = app(LibraryCirculationService::class)->return($issue, 5, null, 'good');

        $this->assertSame('15.00', $returned->fine_amount);
        $this->assertSame('available', $copy->fresh()->status);
        $this->assertSame('good', $copy->fresh()->condition);
    }

    private function studentAndCopy(string $studentStatus = 'active'): array
    {
        $branch = Branch::factory()->create(['status' => true]);
        $category = BookCategory::create([
            'name' => 'Test '.Str::random(6),
            'slug' => Str::lower(Str::random(10)),
            'status' => true,
        ]);
        $book = Book::create([
            'book_category_id' => $category->id,
            'title' => 'Integrity Test Book',
            'author' => 'Test Author',
            'status' => true,
        ]);
        $copy = BookCopy::create([
            'book_id' => $book->id,
            'branch_id' => $branch->id,
            'accession_no' => 'ACC-'.Str::upper(Str::random(8)),
            'condition' => 'good',
            'status' => 'available',
        ]);
        $student = Student::create([
            'branch_id' => $branch->id,
            'student_code' => 'LIB-'.Str::upper(Str::random(8)),
            'qr_token' => (string) Str::uuid(),
            'name' => 'Library Student',
            'mobile' => (string) random_int(7000000000, 9999999999),
            'joining_date' => today(),
            'status' => $studentStatus,
        ]);

        return [$student, $copy];
    }
}
