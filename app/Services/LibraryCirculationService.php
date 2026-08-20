<?php

namespace App\Services;

use App\Models\BookCopy;
use App\Models\BookIssue;
use App\Models\Student;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class LibraryCirculationService
{
    public function __construct(
        private readonly SettingsService $settings
    ) {
    }

    public function issue(Student $student, BookCopy $copy, ?int $issueDays = null, ?int $userId = null): BookIssue
    {
        $issueDays ??= (int) $this->settings->get('book_issue_days', 14, $student->branch_id);

        return DB::transaction(function () use ($student, $copy, $issueDays, $userId) {
            $lockedCopy = BookCopy::query()
                ->whereKey($copy->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ((int) $lockedCopy->branch_id !== (int) $student->branch_id) {
                throw ValidationException::withMessages([
                    'book_copy_id' => 'Selected book copy does not belong to the student branch.',
                ]);
            }

            if ($lockedCopy->status !== 'available') {
                throw ValidationException::withMessages([
                    'book_copy_id' => 'Selected book copy is not available.',
                ]);
            }

            $issue = BookIssue::create([
                'student_id' => $student->id,
                'book_copy_id' => $lockedCopy->id,
                'issued_at' => today(),
                'due_at' => today()->addDays(max(1, $issueDays)),
                'status' => 'issued',
                'issued_by' => $userId,
            ]);

            $lockedCopy->update(['status' => 'issued']);

            return $issue;
        });
    }

    public function return(BookIssue $issue, ?float $finePerDay = null, ?int $userId = null): BookIssue
    {
        $branchId = $issue->student?->branch_id;
        $finePerDay ??= (float) $this->settings->get('book_fine_per_day', 5, $branchId);

        return DB::transaction(function () use ($issue, $finePerDay, $userId) {
            $lockedIssue = BookIssue::query()
                ->with(['student', 'bookCopy'])
                ->whereKey($issue->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedIssue->status === 'returned') {
                throw ValidationException::withMessages([
                    'issue' => 'This book has already been returned.',
                ]);
            }

            $lateDays = $lockedIssue->due_at && $lockedIssue->due_at->lt(today())
                ? $lockedIssue->due_at->diffInDays(today())
                : 0;

            $fine = round($lateDays * max(0, $finePerDay), 2);

            $lockedIssue->update([
                'returned_at' => today(),
                'fine_amount' => $fine,
                'status' => 'returned',
                'returned_by' => $userId,
            ]);

            $lockedIssue->bookCopy()->update(['status' => 'available']);

            return $lockedIssue->fresh(['student', 'bookCopy.book']);
        });
    }
}
