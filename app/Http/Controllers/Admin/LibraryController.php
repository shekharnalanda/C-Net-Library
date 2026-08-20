<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BookCopy;
use App\Models\BookIssue;
use App\Models\BookReservation;
use App\Models\LibraryChargePayment;
use App\Models\Student;
use App\Services\AdminBranchScope;
use App\Services\AuditService;
use App\Services\LibraryCirculationService;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class LibraryController extends Controller
{
    public function __construct(private readonly LibraryCirculationService $circulation)
    {
    }

    public function index(Request $request, AdminBranchScope $branchScope): View
    {
        BookReservation::query()
            ->where('status', 'active')
            ->where('expires_at', '<=', now())
            ->orderBy('id')
            ->chunkById(100, function ($reservations) {
                foreach ($reservations as $reservation) {
                    DB::transaction(function () use ($reservation) {
                        $locked = BookReservation::query()->whereKey($reservation->id)->lockForUpdate()->first();
                        if (! $locked || $locked->status !== 'active' || $locked->expires_at->gt(now())) {
                            return;
                        }

                        $copy = BookCopy::query()->whereKey($locked->book_copy_id)->lockForUpdate()->first();
                        $locked->update(['status' => 'expired', 'cancelled_at' => now()]);
                        if ($copy?->status === 'reserved') {
                            $copy->update(['status' => 'available']);
                        }
                    }, 3);
                }
            });

        $copies = $branchScope->apply(BookCopy::query(), $request->user())
            ->with(['book.category', 'branch', 'reservations' => fn ($q) => $q->where('status', 'active')->with('student')])
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
            ->with(['student', 'bookCopy.book', 'chargePayments'])
            ->whereHas('student', fn ($query) => $branchScope->apply($query, $request->user()))
            ->whereIn('status', ['issued', 'overdue', 'returned', 'lost'])
            ->orderByRaw("CASE WHEN status IN ('issued','overdue') THEN 0 ELSE 1 END")
            ->orderBy('due_at')
            ->limit(75)
            ->get();

        $students = $branchScope->apply(Student::query(), $request->user())
            ->where('status', 'active')
            ->orderBy('name')
            ->get(['id', 'branch_id', 'name', 'student_code']);

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

    public function reserve(Request $request, AuditService $audit): RedirectResponse
    {
        $data = $request->validate([
            'student_id' => ['required', 'exists:students,id'],
            'book_copy_id' => ['required', 'exists:book_copies,id'],
            'expires_at' => ['required', 'date', 'after:now'],
            'remarks' => ['nullable', 'string', 'max:1000'],
        ]);

        $student = Student::query()->whereKey($data['student_id'])->firstOrFail();
        abort_unless($student->status === 'active', 422, 'Only active students can reserve books.');

        $reservation = DB::transaction(function () use ($request, $student, $data) {
            $copy = BookCopy::query()->whereKey($data['book_copy_id'])->lockForUpdate()->firstOrFail();

            abort_unless(
                $request->user()->isGlobalAdmin()
                || ((int) $student->branch_id === (int) $request->user()->branch_id
                    && (int) $copy->branch_id === (int) $request->user()->branch_id),
                403
            );

            if ((int) $student->branch_id !== (int) $copy->branch_id) {
                throw ValidationException::withMessages(['book_copy_id' => 'Book copy and student must belong to the same branch.']);
            }

            if ($copy->status !== 'available') {
                throw ValidationException::withMessages(['book_copy_id' => 'Only an available copy can be reserved.']);
            }

            $activeExists = BookReservation::query()
                ->where('book_copy_id', $copy->id)
                ->where('status', 'active')
                ->exists();
            if ($activeExists) {
                throw ValidationException::withMessages(['book_copy_id' => 'This copy already has an active reservation.']);
            }

            $reservation = BookReservation::create([
                'book_copy_id' => $copy->id,
                'student_id' => $student->id,
                'status' => 'active',
                'reserved_at' => now(),
                'expires_at' => $data['expires_at'],
                'created_by' => auth()->id(),
                'remarks' => $data['remarks'] ?? null,
            ]);
            $copy->update(['status' => 'reserved']);

            return $reservation;
        }, 3);

        $audit->log('library.book.reserved', $reservation, [], $reservation->only([
            'book_copy_id', 'student_id', 'status', 'reserved_at', 'expires_at', 'remarks',
        ]), $request);

        return back()->with('success', 'Book copy reserved successfully.');
    }

    public function cancelReservation(Request $request, BookReservation $reservation, AuditService $audit): RedirectResponse
    {
        $old = $reservation->only(['status', 'cancelled_at']);

        DB::transaction(function () use ($request, $reservation) {
            $locked = BookReservation::query()->with('student')->whereKey($reservation->id)->lockForUpdate()->firstOrFail();
            abort_unless($locked->status === 'active', 422, 'Reservation is already closed.');
            abort_unless(
                $request->user()->isGlobalAdmin()
                || (int) $locked->student?->branch_id === (int) $request->user()->branch_id,
                403
            );

            $copy = BookCopy::query()->whereKey($locked->book_copy_id)->lockForUpdate()->firstOrFail();
            $locked->update([
                'status' => 'cancelled',
                'cancelled_at' => now(),
                'closed_by' => auth()->id(),
            ]);
            if ($copy->status === 'reserved') {
                $copy->update(['status' => 'available']);
            }
        }, 3);

        $reservation->refresh();
        $audit->log('library.reservation.cancelled', $reservation, $old, $reservation->only(['status', 'cancelled_at']), $request);

        return back()->with('success', 'Reservation cancelled.');
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

    public function collectCharge(Request $request, BookIssue $bookIssue, AuditService $audit): RedirectResponse
    {
        $data = $request->validate([
            'charge_type' => ['required', Rule::in(['fine', 'loss'])],
            'amount' => ['required', 'numeric', 'min:0.01', 'max:99999999.99'],
            'payment_mode' => ['required', Rule::in(['cash', 'upi', 'card', 'bank_transfer', 'other'])],
            'transaction_ref' => ['nullable', 'string', 'max:255'],
            'remarks' => ['nullable', 'string', 'max:1000'],
        ]);

        $transactionRef = trim((string) ($data['transaction_ref'] ?? ''));

        try {
            $payment = DB::transaction(function () use ($bookIssue, $data, $transactionRef) {
                $issue = BookIssue::query()->whereKey($bookIssue->id)->lockForUpdate()->firstOrFail();
                $charge = $data['charge_type'] === 'fine' ? (float) $issue->fine_amount : (float) $issue->loss_charge;
                $paid = (float) LibraryChargePayment::query()
                    ->where('book_issue_id', $issue->id)
                    ->where('charge_type', $data['charge_type'])
                    ->sum('amount');
                $outstanding = max(0, $charge - $paid);

                if ($outstanding <= 0) {
                    throw ValidationException::withMessages(['amount' => 'This library charge is already fully settled.']);
                }
                if ((float) $data['amount'] > $outstanding) {
                    throw ValidationException::withMessages(['amount' => 'Payment cannot exceed the outstanding library charge.']);
                }

                $payment = LibraryChargePayment::create([
                    'book_issue_id' => $issue->id,
                    'charge_type' => $data['charge_type'],
                    'amount' => $data['amount'],
                    'payment_date' => today(),
                    'payment_mode' => $data['payment_mode'],
                    'transaction_ref' => $transactionRef !== '' ? $transactionRef : null,
                    'received_by' => auth()->id(),
                    'remarks' => $data['remarks'] ?? null,
                ]);

                if ($data['charge_type'] === 'fine') {
                    $issue->update(['fine_paid' => min((float) $issue->fine_amount, $paid + (float) $data['amount'])]);
                }

                return $payment;
            }, 3);
        } catch (QueryException $exception) {
            if ($transactionRef !== '' && in_array((string) $exception->getCode(), ['23000', '23505'], true)) {
                throw ValidationException::withMessages(['transaction_ref' => 'This library transaction reference has already been recorded.']);
            }
            throw $exception;
        }

        $audit->log('library.charge.collected', $payment, [], $payment->only([
            'book_issue_id', 'charge_type', 'amount', 'payment_date', 'payment_mode', 'transaction_ref',
        ]), $request);

        return back()->with('success', 'Library charge payment recorded.');
    }

    public function recoverLost(Request $request, BookIssue $bookIssue, AuditService $audit): RedirectResponse
    {
        $data = $request->validate([
            'condition' => ['required', Rule::in(['good', 'fair', 'damaged'])],
            'remarks' => ['required', 'string', 'max:1000'],
        ]);

        $old = $bookIssue->only(['status', 'loss_charge', 'remarks']);
        DB::transaction(function () use ($bookIssue, $data) {
            $issue = BookIssue::query()->whereKey($bookIssue->id)->lockForUpdate()->firstOrFail();
            abort_unless($issue->status === 'lost', 422, 'Only a lost issue can be recovered.');

            $paid = (float) LibraryChargePayment::query()
                ->where('book_issue_id', $issue->id)
                ->where('charge_type', 'loss')
                ->sum('amount');
            abort_unless($paid >= (float) $issue->loss_charge, 422, 'Settle the loss charge before recovering this copy.');

            $copy = BookCopy::query()->whereKey($issue->book_copy_id)->lockForUpdate()->firstOrFail();
            $issue->update([
                'status' => 'returned',
                'returned_at' => today(),
                'return_condition' => $data['condition'],
                'remarks' => trim(($issue->remarks ? $issue->remarks."\n" : '').'Recovered: '.$data['remarks']),
            ]);
            $copy->update([
                'condition' => $data['condition'],
                'status' => $data['condition'] === 'damaged' ? 'damaged' : 'available',
            ]);
        }, 3);

        $bookIssue->refresh();
        $audit->log('library.book.recovered', $bookIssue, $old, $bookIssue->only([
            'status', 'returned_at', 'return_condition', 'remarks',
        ]), $request);

        return back()->with('success', 'Lost book recovery recorded.');
    }
}
