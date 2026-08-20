<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BookCopy;
use App\Models\BookIssue;
use App\Models\Student;
use App\Services\AdminBranchScope;
use App\Services\AuditService;
use App\Services\LibraryCirculationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class LibraryController extends Controller
{
    public function __construct(private readonly LibraryCirculationService $circulation)
    {
    }

    public function index(Request $request, AdminBranchScope $branchScope): View
    {
        $copies = $branchScope->apply(BookCopy::query(), $request->user())
            ->with(['book.category', 'branch'])
            ->when($request->search, function ($query, $search) {
                $query->where(function ($q) use ($search) {
                    $q->where('accession_no', 'like', "%{$search}%")
                        ->orWhere('barcode', 'like', "%{$search}%")
                        ->orWhereHas('book', function ($book) use ($search) {
                            $book->where('title', 'like', "%{$search}%")
                                ->orWhere('author', 'like', "%{$search}%");
                        });
                });
            })
            ->orderBy('status')
            ->orderBy('id')
            ->paginate(30)
            ->withQueryString();

        $issues = BookIssue::query()
            ->with(['student', 'bookCopy.book'])
            ->whereHas('student', fn ($query) => $branchScope->apply($query, $request->user()))
            ->whereIn('status', ['issued', 'overdue'])
            ->orderBy('due_at')
            ->limit(50)
            ->get();

        $students = $branchScope->apply(Student::query(), $request->user())
            ->where('status', 'active')
            ->orderBy('name')
            ->get(['id', 'name', 'student_code']);

        return view('admin.library.index', compact('copies', 'issues', 'students'));
    }

    public function issue(Request $request, AuditService $audit): RedirectResponse
    {
        $data = $request->validate([
            'student_id' => ['required', 'exists:students,id'],
            'book_copy_id' => ['required', 'exists:book_copies,id'],
            'issue_days' => ['nullable', 'integer', 'min:1', 'max:90'],
        ]);

        $student = Student::findOrFail($data['student_id']);
        $copy = BookCopy::with('book')->findOrFail($data['book_copy_id']);

        abort_unless(
            $request->user()->isGlobalAdmin()
            || ((int) $student->branch_id === (int) $request->user()->branch_id
                && (int) $copy->branch_id === (int) $request->user()->branch_id),
            403
        );

        $issue = $this->circulation->issue(
            $student,
            $copy,
            isset($data['issue_days']) ? (int) $data['issue_days'] : null,
            auth()->id(),
        );

        $audit->log('library.book.issued', $issue, [], [
            'student_id' => $student->id,
            'book_copy_id' => $copy->id,
            'book_id' => $copy->book_id,
            'issued_at' => $issue->issued_at?->toDateString(),
            'due_at' => $issue->due_at?->toDateString(),
            'status' => $issue->status,
        ]);

        return back()->with('success', 'Book issued successfully.');
    }

    public function return(Request $request, BookIssue $bookIssue, AuditService $audit): RedirectResponse
    {
        $data = $request->validate([
            'return_condition' => ['nullable', Rule::in(['good', 'fair', 'damaged'])],
        ]);

        $bookIssue->loadMissing(['student', 'bookCopy.book']);
        $oldValues = [
            'status' => $bookIssue->status,
            'due_at' => $bookIssue->due_at?->toDateString(),
            'returned_at' => $bookIssue->returned_at?->toDateString(),
            'return_condition' => $bookIssue->return_condition,
            'fine_amount' => (float) $bookIssue->fine_amount,
            'book_copy_status' => $bookIssue->bookCopy?->status,
        ];

        $returnedIssue = $this->circulation->return(
            $bookIssue,
            null,
            auth()->id(),
            $data['return_condition'] ?? 'good',
        );

        $audit->log('library.book.returned', $returnedIssue, $oldValues, [
            'status' => $returnedIssue->status,
            'returned_at' => $returnedIssue->returned_at?->toDateString(),
            'return_condition' => $returnedIssue->return_condition,
            'fine_amount' => (float) $returnedIssue->fine_amount,
            'book_copy_id' => $returnedIssue->book_copy_id,
            'book_copy_status' => $returnedIssue->bookCopy?->status,
            'student_id' => $returnedIssue->student_id,
        ]);

        return back()->with('success', 'Book return recorded. Fine calculated if overdue.');
    }

    public function lost(Request $request, BookIssue $bookIssue, AuditService $audit): RedirectResponse
    {
        $data = $request->validate([
            'loss_charge' => ['nullable', 'numeric', 'min:0', 'max:99999999.99'],
            'remarks' => ['required', 'string', 'max:1000'],
        ]);

        $bookIssue->loadMissing(['student', 'bookCopy.book']);
        $oldValues = [
            'status' => $bookIssue->status,
            'loss_charge' => (float) $bookIssue->loss_charge,
            'book_copy_status' => $bookIssue->bookCopy?->status,
        ];

        $lostIssue = $this->circulation->markLost(
            $bookIssue,
            (float) ($data['loss_charge'] ?? 0),
            auth()->id(),
            $data['remarks'],
        );

        $audit->log('library.book.lost', $lostIssue, $oldValues, [
            'status' => $lostIssue->status,
            'loss_charge' => (float) $lostIssue->loss_charge,
            'book_copy_id' => $lostIssue->book_copy_id,
            'book_copy_status' => $lostIssue->bookCopy?->status,
            'student_id' => $lostIssue->student_id,
            'remarks' => $lostIssue->remarks,
        ]);

        return back()->with('success', 'Book marked lost. Copy is no longer available for circulation.');
    }
}
