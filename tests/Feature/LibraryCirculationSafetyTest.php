<?php

namespace Tests\Feature;

use App\Models\Book;
use App\Models\BookCopy;
use App\Models\Branch;
use App\Models\Student;
use App\Services\LibraryCirculationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class LibraryCirculationSafetyTest extends TestCase
{
    use RefreshDatabase;

    public function test_book_copy_from_another_branch_cannot_be_issued_to_student(): void
    {
        $studentBranch = Branch::factory()->create(['status' => true]);
        $copyBranch = Branch::factory()->create(['status' => true]);
        $student = Student::create([
            'branch_id' => $studentBranch->id,
            'student_code' => 'LIB-'.Str::upper(Str::random(8)),
            'qr_token' => (string) Str::uuid(),
            'name' => 'Library Student',
            'mobile' => '9000000221',
            'joining_date' => today(),
            'status' => 'active',
        ]);
        $book = Book::create([
            'title' => 'Cross Branch Book',
            'status' => true,
        ]);
        $copy = BookCopy::create([
            'book_id' => $book->id,
            'branch_id' => $copyBranch->id,
            'accession_no' => 'ACC-'.Str::upper(Str::random(8)),
            'status' => 'available',
            'condition' => 'good',
        ]);

        $this->expectException(ValidationException::class);

        app(LibraryCirculationService::class)->issue($student, $copy);
    }

    public function test_returning_same_issue_twice_is_rejected(): void
    {
        $branch = Branch::factory()->create(['status' => true]);
        $student = Student::create([
            'branch_id' => $branch->id,
            'student_code' => 'RET-'.Str::upper(Str::random(8)),
            'qr_token' => (string) Str::uuid(),
            'name' => 'Return Student',
            'mobile' => '9000000222',
            'joining_date' => today(),
            'status' => 'active',
        ]);
        $book = Book::create([
            'title' => 'Return Safety Book',
            'status' => true,
        ]);
        $copy = BookCopy::create([
            'book_id' => $book->id,
            'branch_id' => $branch->id,
            'accession_no' => 'ACC-'.Str::upper(Str::random(8)),
            'status' => 'available',
            'condition' => 'good',
        ]);

        $service = app(LibraryCirculationService::class);
        $issue = $service->issue($student, $copy);
        $service->return($issue);

        $this->expectException(ValidationException::class);
        $service->return($issue->fresh());
    }
}
