<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Student;
use App\Services\AttendanceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class QrAttendanceController extends Controller
{
    public function scan(Request $request): View
    {
        $student = null;

        if ($request->filled('token')) {
            $student = Student::query()
                ->with(['branch', 'activeMembership.studySlot'])
                ->where('qr_token', $request->string('token'))
                ->first();
        }

        return view('admin.attendance.scan', compact('student'));
    }

    public function mark(
        Request $request,
        Student $student,
        AttendanceService $attendanceService
    ): RedirectResponse {
        $request->validate([
            'token' => ['required', 'string'],
            'action' => ['required', 'in:check_in,check_out'],
        ]);

        abort_unless(hash_equals((string) $student->qr_token, (string) $request->input('token')), 403);

        if ($request->input('action') === 'check_out') {
            $attendanceService->checkOut($student, (int) auth()->id(), 'QR scan', 'qr');
            return back()->with('success', 'Student checked out successfully.');
        }

        $attendanceService->checkIn($student, (int) auth()->id(), 'qr', 'QR scan');

        return back()->with('success', 'Student checked in successfully.');
    }
}
