<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Student;
use App\Services\AttendanceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class QrAttendanceController extends Controller
{
    public function scan(Request $request): Response
    {
        return $this->scannerResponse();
    }

    public function lookup(Request $request): Response
    {
        $data = $request->validate([
            'token' => ['required', 'string', 'max:255'],
        ]);

        $branchId = $request->user()->scopedBranchId();

        $student = Student::query()
            ->with(['branch', 'activeMembership.studySlot'])
            ->where('qr_token', $data['token'])
            ->when($branchId !== null, fn ($query) => $query->where('branch_id', $branchId))
            ->first();

        return $this->scannerResponse($student, $student ? $data['token'] : null, true);
    }

    public function mark(
        Request $request,
        Student $student,
        AttendanceService $attendanceService
    ): RedirectResponse {
        $request->validate([
            'token' => ['required', 'string', 'max:255'],
            'action' => ['required', 'in:check_in,check_out'],
        ]);

        abort_unless(hash_equals((string) $student->qr_token, (string) $request->input('token')), 403);

        if ($request->input('action') === 'check_out') {
            $attendanceService->checkOut($student, (int) auth()->id(), 'QR scan', 'qr');

            return redirect()->route('admin.attendance.scan')
                ->with('success', 'Student checked out successfully.');
        }

        $attendanceService->checkIn($student, (int) auth()->id(), 'qr', 'QR scan');

        return redirect()->route('admin.attendance.scan')
            ->with('success', 'Student checked in successfully.');
    }

    private function scannerResponse(
        ?Student $student = null,
        ?string $token = null,
        bool $lookupAttempted = false,
    ): Response {
        return response()
            ->view('admin.attendance.scan', compact('student', 'token', 'lookupAttempted'))
            ->header('Cache-Control', 'private, no-store, no-cache, must-revalidate')
            ->header('Pragma', 'no-cache')
            ->header('Referrer-Policy', 'no-referrer')
            ->header('X-Robots-Tag', 'noindex, nofollow, noarchive');
    }
}
