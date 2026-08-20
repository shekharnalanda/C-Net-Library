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
        if ($student->status !== 'active') {
            throw ValidationException::withMessages([
                'student_id' => 'Only active students can borrow books.',
            ]);
        }

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

            $openIssueExists = BookIssue::query()
                ->where('book_copy_id', $lockedCopy->id)
                ->whereIn('status', ['issued', 'overdue'])
                ->exists();

            if ($openIssueExists) {
                throw ValidationException::withMessages([
                    'book_copy_id' => 'Selected book copy already has an open issue record.',
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
        }, 3);
    }

    public function return(
        BookIssue $issue,
        ?float $finePerDay = null,
        ?int $userId = null,
        string $returnCondition = 'good'
    ): BookIssue {
        if (! in_array($returnCondition, ['good', 'fair', 'damaged'], true)) {
            throw ValidationException::withMessages([
                'return_condition' => 'Invalid return condition.',
            ]);
        }

        $branchId = $issue->student?->branch_id;
        $finePerDay ??= (float) $this->settings->get('book_fine_per_day', 5, $branchId);

        return DB::transaction(function () use ($issue, $finePerDay, $userId, $returnCondition) {
            $lockedIssue = BookIssue::query()
                ->with(['student', 'bookCopy'])
                ->whereKey($issue->id)
                ->lockForUpdate()
                ->firstOrFail();

            if (in_array($lockedIssue->status, ['returned', 'lost'], true)) {
                throw ValidationException::withMessages([
                    'issue' => 'This issue is already closed.',
                ]);
            }

            $lockedCopy = BookCopy::query()
                ->whereKey($lockedIssue->book_copy_id)
                ->lockForUpdate()
                ->firstOrFail();

            if (in_array($lockedCopy->status, ['lost', 'damaged'], true)) {
                throw ValidationException::withMessages([
                    'book_copy_id' => 'This copy is already marked lost or damaged and cannot use the normal return flow.',
                ]);
            }

            $lateDays = $lockedIssue->due_at && $lockedIssue->due_at->lt(today())
                ? $lockedIssue->due_at->diffInDays(today())
                : 0;

            $fine = round($lateDays * max(0, $finePerDay), 2);

            $lockedIssue->update([
                'returned_at' => today(),
                'return_condition' => $returnCondition,
                'fine_amount' => $fine,
                'status' => 'returned',
                'returned_by' => $userId,
            ]);

            if ($returnCondition === 'damaged') {
                $lockedCopy->update([
                    'condition' => 'damaged',
                    'status' => 'damaged',
                ]);
            } else {
                $lockedCopy->update([
                    'condition' => $returnCondition,
                    'status' => 'available',
                ]);
            }

            return $lockedIssue->fresh(['student', 'bookCopy.book']);
        }, 3);
    }

    public function markLost(BookIssue $issue, float $lossCharge = 0, ?int $userId = null, ?string $remarks = null): BookIssue
    {
        return DB::transaction(function () use ($issue, $lossCharge, $userId, $remarks) {
            $lockedIssue = BookIssue::query()
                ->with(['student', 'bookCopy'])
                ->whereKey($issue->id)
                ->lockForUpdate()
                ->firstOrFail();

            if (in_array($lockedIssue->status, ['returned', 'lost'], true)) {
                throw ValidationException::withMessages([
                    'issue' => 'This issue is already closed.',
                ]);
            }

            $lockedCopy = BookCopy::query()
                ->whereKey($lockedIssue->book_copy_id)
                ->lockForUpdate()
                ->firstOrFail();

            $lockedIssue->update([
                'status' => 'lost',
                'loss_charge' => max(0, $lossCharge),
                'returned_by' => $userId,
                'remarks' => $remarks ?: $lockedIssue->remarks,
            ]);

            $lockedCopy->update(['status' => 'lost']);

            return $lockedIssue->fresh(['student', 'bookCopy.book']);
        }, 3);
    }
}
