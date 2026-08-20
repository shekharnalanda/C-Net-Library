<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\Student;
use App\Services\AdminBranchScope;
use App\Services\AttendanceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AttendanceController extends Controller
{
    public function index(Request $request, AdminBranchScope $branchScope): View
    {
        $attendances = Attendance::query()
            ->with(['student.branch'])
            ->whereHas('student', fn ($query) => $branchScope->apply($query, $request->user()))
            ->when($request->filled('date'), fn ($query) => $query->whereDate('attendance_date', $request->date))
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = $request->string('search')->toString();
                $query->whereHas('student', function ($studentQuery) use ($search) {
                    $studentQuery->where('name', 'like', "%{$search}%")
                        ->orWhere('student_code', 'like', "%{$search}%")
                        ->orWhere('mobile', 'like', "%{$search}%");
                });
            })
            ->latest('check_in_at')
            ->paginate(30)
            ->withQueryString();

        $presentToday = Attendance::query()
            ->whereHas('student', fn ($query) => $branchScope->apply($query, $request->user()))
            ->whereDate('attendance_date', today())
            ->distinct('student_id')
            ->count('student_id');

        $currentlyInside = Attendance::query()
            ->whereHas('student', fn ($query) => $branchScope->apply($query, $request->user()))
            ->whereNull('check_out_at')
            ->count();

        return view('admin.attendance.index', compact('attendances', 'presentToday', 'currentlyInside'));
    }

    public function checkIn(Request $request, Student $student, AttendanceService $service): RedirectResponse
    {
        $data = $request->validate([
            'entry_method' => ['nullable', 'in:manual,qr,member_id'],
            'remarks' => ['nullable', 'string', 'max:1000'],
        ]);

        $service->checkIn(
            $student,
            auth()->id(),
            $data['entry_method'] ?? 'manual',
            $data['remarks'] ?? null,
        );

        return back()->with('success', 'Student checked in successfully.');
    }

    public function checkOut(Request $request, Student $student, AttendanceService $service): RedirectResponse
    {
        $data = $request->validate([
            'remarks' => ['nullable', 'string', 'max:1000'],
        ]);

        $service->checkOut($student, auth()->id(), $data['remarks'] ?? null);

        return back()->with('success', 'Student checked out successfully.');
    }
}
