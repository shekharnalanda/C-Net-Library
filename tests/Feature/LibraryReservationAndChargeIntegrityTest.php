<?php

namespace Tests\Feature;

use App\Models\Book;
use App\Models\BookCopy;
use App\Models\BookIssue;
use App\Models\BookReservation;
use App\Models\Branch;
use App\Models\LibraryChargePayment;
use App\Models\Student;
use App\Services\LibraryCirculationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class LibraryReservationAndChargeIntegrityTest extends TestCase
{
    use RefreshDatabase;

    public function test_reserved_copy_can_only_be_issued_to_reservation_owner(): void
    {
        [$branch, $owner, $other, $copy] = $this->fixtures();

        BookReservation::create([
            'book_copy_id' => $copy->id,
            'student_id' => $owner->id,
            'status' => 'active',
            'reserved_at' => now(),
            'expires_at' => now()->addHour(),
        ]);
        $copy->update(['status' => 'reserved']);

        $this->expectException(ValidationException::class);
        app(LibraryCirculationService::class)->issue($other, $copy);
    }

    public function test_expired_reservation_is_released_when_owner_attempts_issue(): void
    {
        [, $owner, , $copy] = $this->fixtures();

        $reservation = BookReservation::create([
            'book_copy_id' => $copy->id,
            'student_id' => $owner->id,
            'status' => 'active',
            'reserved_at' => now()->subHours(2),
            'expires_at' => now()->subHour(),
        ]);
        $copy->update(['status' => 'reserved']);

        $issue = app(LibraryCirculationService::class)->issue($owner, $copy);

        $this->assertSame('issued', $issue->status);
        $this->assertSame('expired', $reservation->fresh()->status);
        $this->assertSame('issued', $copy->fresh()->status);
    }

    public function test_unpaid_library_charge_blocks_new_borrowing_until_settled(): void
    {
        [$branch, $student, , $copy] = $this->fixtures();
        $book2 = Book::create(['title' => 'Second Book', 'author' => 'Author']);
        $copy2 = BookCopy::create([
            'book_id' => $book2->id,
            'branch_id' => $branch->id,
            'accession_no' => 'ACC-'.Str::upper(Str::random(8)),
            'condition' => 'good',
            'status' => 'available',
        ]);

        $issue = BookIssue::create([
            'student_id' => $student->id,
            'book_copy_id' => $copy->id,
            'issued_at' => today()->subDays(10),
            'due_at' => today()->subDays(5),
            'returned_at' => today(),
            'fine_amount' => 25,
            'status' => 'returned',
        ]);

        try {
            app(LibraryCirculationService::class)->issue($student, $copy2);
            $this->fail('Expected outstanding-charge validation error.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('student_id', $exception->errors());
        }

        LibraryChargePayment::create([
            'book_issue_id' => $issue->id,
            'charge_type' => 'fine',
            'amount' => 25,
            'payment_date' => today(),
            'payment_mode' => 'cash',
        ]);

        $newIssue = app(LibraryCirculationService::class)->issue($student, $copy2);
        $this->assertSame('issued', $newIssue->status);
    }

    public function test_loss_charge_must_be_settled_before_recovery(): void
    {
        [$branch, $student, , $copy] = $this->fixtures();
        $issue = BookIssue::create([
            'student_id' => $student->id,
            'book_copy_id' => $copy->id,
            'issued_at' => today()->subDays(2),
            'due_at' => today()->addDays(5),
            'loss_charge' => 500,
            'status' => 'lost',
        ]);
        $copy->update(['status' => 'lost']);

        $this->assertSame(0, LibraryChargePayment::query()->where('book_issue_id', $issue->id)->count());
        $this->assertSame('lost', $copy->fresh()->status);

        LibraryChargePayment::create([
            'book_issue_id' => $issue->id,
            'charge_type' => 'loss',
            'amount' => 500,
            'payment_date' => today(),
            'payment_mode' => 'cash',
        ]);

        $this->assertSame(500.0, (float) LibraryChargePayment::query()->where('book_issue_id', $issue->id)->sum('amount'));
    }

    private function fixtures(): array
    {
        $branch = Branch::factory()->create(['status' => true]);
        $student = Student::create([
            'branch_id' => $branch->id,
            'student_code' => 'LIB-'.Str::upper(Str::random(8)),
            'qr_token' => (string) Str::uuid(),
            'name' => 'Library Owner',
            'mobile' => '9000000101',
            'joining_date' => today(),
            'status' => 'active',
        ]);
        $other = Student::create([
            'branch_id' => $branch->id,
            'student_code' => 'LIB-'.Str::upper(Str::random(8)),
            'qr_token' => (string) Str::uuid(),
            'name' => 'Other Student',
            'mobile' => '9000000102',
            'joining_date' => today(),
            'status' => 'active',
        ]);
        $book = Book::create(['title' => 'Reservation Test', 'author' => 'Author']);
        $copy = BookCopy::create([
            'book_id' => $book->id,
            'branch_id' => $branch->id,
            'accession_no' => 'ACC-'.Str::upper(Str::random(8)),
            'condition' => 'good',
            'status' => 'available',
        ]);

        return [$branch, $student, $other, $copy];
    }
}
