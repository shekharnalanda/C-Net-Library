<?php

namespace App\Services;

use App\Models\BookCopy;
use App\Models\BookIssue;
use App\Models\Student;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class LibraryCirculationService
{
    public function issue(Student $student, BookCopy $copy, int $issueDays = 14, ?int $userId = null): BookIssue
    {
        if ($copy->status !== 'available') {
            throw ValidationException::withMessages([
                'book_copy_id' => 'Selected book copy is not available.',
            ]);
        }

        return DB::transaction(function () use ($student, $copy, $issueDays, $userId) {
            $issue = BookIssue::create([
                'student_id' => $student->id,
                'book_copy_id' => $copy->id,
                'issued_at' => today(),
                'due_at' => today()->addDays(max(1, $issueDays)),
                'status' => 'issued',
                'issued_by' => $userId,
            ]);

            $copy->update(['status' => 'issued']);

            return $issue;
        });
    }

    public function return(BookIssue $issue, float $finePerDay = 5, ?int $userId = null): BookIssue
    {
        if ($issue->status === 'returned') {
            throw ValidationException::withMessages([
                'issue' => 'This book has already been returned.',
            ]);
        }

        return DB::transaction(function () use ($issue, $finePerDay, $userId) {
            $lateDays = $issue->due_at && $issue->due_at->lt(today())
                ? $issue->due_at->diffInDays(today())
                : 0;

            $fine = round($lateDays * max(0, $finePerDay), 2);

            $issue->update([
                'returned_at' => today(),
                'fine_amount' => $fine,
                'status' => 'returned',
                'returned_by' => $userId,
            ]);

            $issue->bookCopy()->update(['status' => 'available']);

            return $issue->fresh(['student', 'bookCopy.book']);
        });
    }
}
