<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\Book;
use App\Models\BookIssue;
use App\Models\Enquiry;
use App\Models\LockerAllocation;
use App\Models\Payment;
use App\Models\Student;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminController extends Controller
{
    public function dashboard(Request $request): JsonResponse
    {
        $user = $this->admin($request);

        $students = $this->studentScope(Student::query(), $user);
        $enquiries = $this->branchColumnScope(Enquiry::query(), $user);
        $attendance = $this->branchColumnScope(Attendance::query(), $user);
        $payments = Payment::query()->when(! $user->isGlobalAdmin(), fn ($q) => $q->whereHas('student', fn ($s) => $s->where('branch_id', $user->branch_id)));
        $issues = BookIssue::query()->when(! $user->isGlobalAdmin(), fn ($q) => $q->whereHas('student', fn ($s) => $s->where('branch_id', $user->branch_id)));
        $lockers = LockerAllocation::query()->when(! $user->isGlobalAdmin(), fn ($q) => $q->whereHas('student', fn ($s) => $s->where('branch_id', $user->branch_id)));

        return response()->json([
            'admin' => $this->adminPayload($user),
            'counts' => [
                'students' => (clone $students)->count(),
                'active_students' => (clone $students)->where('status', 'active')->count(),
                'enquiries' => (clone $enquiries)->count(),
                'today_attendance' => (clone $attendance)->whereDate('check_in_at', today())->count(),
                'books' => Book::query()->count(),
                'active_book_issues' => (clone $issues)->whereIn('status', ['issued', 'overdue'])->count(),
                'active_locker_allocations' => (clone $lockers)->where('status', 'active')->count(),
            ],
            'finance' => [
                'today_collection' => (float) (clone $payments)->whereDate('payment_date', today())->whereIn('payment_status', ['paid', 'partial'])->sum('amount'),
                'month_collection' => (float) (clone $payments)->whereBetween('payment_date', [now()->startOfMonth()->toDateString(), now()->endOfMonth()->toDateString()])->whereIn('payment_status', ['paid', 'partial'])->sum('amount'),
            ],
        ]);
    }

    public function students(Request $request): JsonResponse
    {
        $user = $this->admin($request);
        $rows = $this->studentScope(Student::query(), $user)->with('branch:id,name')->latest('id')->paginate($this->perPage($request));
        return response()->json($rows);
    }

    public function enquiries(Request $request): JsonResponse
    {
        $user = $this->admin($request);
        return response()->json($this->branchColumnScope(Enquiry::query(), $user)->latest('id')->paginate($this->perPage($request)));
    }

    public function payments(Request $request): JsonResponse
    {
        $user = $this->admin($request);
        return response()->json(Payment::query()->with('student:id,student_code,name,branch_id')
            ->when(! $user->isGlobalAdmin(), fn ($q) => $q->whereHas('student', fn ($s) => $s->where('branch_id', $user->branch_id)))
            ->latest('payment_date')->latest('id')->paginate($this->perPage($request)));
    }

    public function attendance(Request $request): JsonResponse
    {
        $user = $this->admin($request);
        return response()->json($this->branchColumnScope(Attendance::query(), $user)->with('student:id,student_code,name')->latest('check_in_at')->paginate($this->perPage($request)));
    }

    public function books(Request $request): JsonResponse
    {
        $this->admin($request);
        return response()->json(Book::query()->latest('id')->paginate($this->perPage($request)));
    }

    public function bookIssues(Request $request): JsonResponse
    {
        $user = $this->admin($request);
        return response()->json(BookIssue::query()->with('student:id,student_code,name,branch_id')
            ->when(! $user->isGlobalAdmin(), fn ($q) => $q->whereHas('student', fn ($s) => $s->where('branch_id', $user->branch_id)))
            ->latest('id')->paginate($this->perPage($request)));
    }

    public function lockers(Request $request): JsonResponse
    {
        $user = $this->admin($request);
        return response()->json(LockerAllocation::query()->with(['locker:id,locker_no,branch_id,monthly_charge','student:id,student_code,name,branch_id'])
            ->when(! $user->isGlobalAdmin(), fn ($q) => $q->whereHas('student', fn ($s) => $s->where('branch_id', $user->branch_id)))
            ->latest('id')->paginate($this->perPage($request)));
    }

    private function admin(Request $request): User
    {
        $user = $request->user();
        $roles = ['super_admin', 'admin', 'branch_admin', 'reception', 'accountant', 'librarian', 'counselor'];
        abort_unless($user && in_array($user->role, $roles, true), 403, 'Admin access required.');
        return $user;
    }

    private function studentScope(Builder $query, User $user): Builder
    {
        return $query->when(! $user->isGlobalAdmin(), fn ($q) => $q->where('branch_id', $user->branch_id));
    }

    private function branchColumnScope(Builder $query, User $user): Builder
    {
        return $query->when(! $user->isGlobalAdmin(), fn ($q) => $q->where('branch_id', $user->branch_id));
    }

    private function perPage(Request $request): int
    {
        return max(1, min(100, (int) $request->integer('per_page', 25)));
    }

    private function adminPayload(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->role,
            'branch_id' => $user->branch_id,
            'global_admin' => $user->isGlobalAdmin(),
        ];
    }
}
