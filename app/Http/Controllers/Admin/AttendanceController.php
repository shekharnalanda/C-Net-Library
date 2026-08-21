<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\Student;
use App\Services\AttendanceService;
use App\Support\AdminBranchScope;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AttendanceController extends Controller
{
    public function index(Request $request): View
    {
        $selectedDate = $request->filled('date')
            ? $request->date('date')->toDateString()
            : today()->toDateString();

        $baseQuery = Attendance::query()
            ->whereHas('student', function ($query) use ($request) {
                AdminBranchScope::apply($query, $request);
            });

        $attendances = (clone $baseQuery)
            ->with(['student.branch'])
            ->whereDate('attendance_date', $selectedDate)
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = trim($request->string('search')->toString());

                $query->whereHas('student', function ($studentQuery) use ($search) {
                    $studentQuery->where(function ($student) use ($search) {
                        $student->where('name', 'like', "%{$search}%")
                            ->orWhere('student_code', 'like', "%{$search}%")
                            ->orWhere('mobile', 'like', "%{$search}%");
                    });
                });
            })
            ->latest('check_in_at')
            ->paginate(30)
            ->withQueryString();

        $dayQuery = (clone $baseQuery)->whereDate('attendance_date', $selectedDate);

        $presentCount = (clone $dayQuery)
            ->distinct('student_id')
            ->count('student_id');

        $currentlyInside = Attendance::query()
            ->whereHas('student', function ($query) use ($request) {
                AdminBranchScope::apply($query, $request);
            })
            ->whereNull('check_out_at')
            ->count();

        $completedSessions = (clone $dayQuery)
            ->whereNotNull('check_out_at')
            ->count();

        $studyMinutes = (int) (clone $dayQuery)->sum('study_minutes');

        return view('admin.attendance.index', compact(
            'attendances',
            'presentCount',
            'currentlyInside',
            'completedSessions',
            'studyMinutes',
            'selectedDate',
        ));
    }

    public function checkIn(Request $request, Student $student, AttendanceService $service): RedirectResponse
    {
        AdminBranchScope::authorize($request, $student->branch_id);

        $data = $request->validate([
            'entry_method' => ['nullable', 'in:manual,qr,member_id'],
            'remarks' => ['nullable', 'string', 'max:1000'],
        ]);

        $service->checkIn(
            $student,
            (int) auth()->id(),
            $data['entry_method'] ?? 'manual',
            $data['remarks'] ?? null,
        );

        return back()->with('success', 'Student checked in successfully.');
    }

    public function checkOut(Request $request, Student $student, AttendanceService $service): RedirectResponse
    {
        AdminBranchScope::authorize($request, $student->branch_id);

        $data = $request->validate([
            'remarks' => ['nullable', 'string', 'max:1000'],
        ]);

        $service->checkOut($student, (int) auth()->id(), $data['remarks'] ?? null);

        return back()->with('success', 'Student checked out successfully.');
    }
}
