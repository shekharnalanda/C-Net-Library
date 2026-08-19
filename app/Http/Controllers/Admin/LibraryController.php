<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BookCopy;
use App\Models\BookIssue;
use App\Models\Student;
use App\Services\LibraryCirculationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class LibraryController extends Controller
{
    public function __construct(private readonly LibraryCirculationService $circulation)
    {
    }

    public function index(Request $request): View
    {
        $copies = BookCopy::query()
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
            ->whereIn('status', ['issued', 'overdue'])
            ->orderBy('due_at')
            ->limit(50)
            ->get();

        $students = Student::query()
            ->where('status', 'active')
            ->orderBy('name')
            ->get(['id', 'name', 'student_code']);

        return view('admin.library.index', compact('copies', 'issues', 'students'));
    }

    public function issue(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'student_id' => ['required', 'exists:students,id'],
            'book_copy_id' => ['required', 'exists:book_copies,id'],
            'issue_days' => ['nullable', 'integer', 'min:1', 'max:90'],
        ]);

        $student = Student::findOrFail($data['student_id']);
        $copy = BookCopy::findOrFail($data['book_copy_id']);

        $this->circulation->issue(
            $student,
            $copy,
            isset($data['issue_days']) ? (int) $data['issue_days'] : null,
            auth()->id(),
        );

        return back()->with('success', 'Book issued successfully.');
    }

    public function return(BookIssue $bookIssue): RedirectResponse
    {
        $bookIssue->loadMissing('student');
        $this->circulation->return($bookIssue, null, auth()->id());

        return back()->with('success', 'Book returned successfully. Fine calculated if overdue.');
    }
}
