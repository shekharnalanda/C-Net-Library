<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Student;
use App\Services\AttendanceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Str;

class QrAttendanceController extends Controller
{
    private const CHALLENGE_TTL_SECONDS = 120;

    public function scan(Request $request): Response
    {
        $challenge = (string) $request->session()->pull('attendance_scan_challenge', '');
        $student = $challenge !== '' ? $this->studentForChallenge($request, $challenge) : null;

        return $this->scannerResponse($student, $student ? $challenge : null, $challenge !== '');
    }

    public function landing(Request $request, string $token): RedirectResponse
    {
        $student = $this->findStudentForToken($request, $token);

        if ($student) {
            $challenge = $this->createChallenge($request, $student, $token);
            $request->session()->put('attendance_scan_challenge', $challenge);
        }

        return redirect()->route('admin.attendance.scan');
    }

    public function lookup(Request $request): Response
    {
        $data = $request->validate([
            'token' => ['required', 'string', 'max:255'],
        ]);

        $student = $this->findStudentForToken($request, $data['token']);
        $challenge = $student ? $this->createChallenge($request, $student, $data['token']) : null;

        return $this->scannerResponse($student, $challenge, true);
    }

    public function mark(
        Request $request,
        Student $student,
        AttendanceService $attendanceService
    ): RedirectResponse {
        $data = $request->validate([
            'challenge' => ['required', 'string', 'size:64'],
            'action' => ['required', 'in:check_in,check_out'],
        ]);

        $this->consumeChallenge($request, $student, $data['challenge']);

        if ($data['action'] === 'check_out') {
            $attendanceService->checkOut($student, (int) auth()->id(), 'QR scan', 'qr');

            return redirect()->route('admin.attendance.scan')
                ->with('success', 'Student checked out successfully.');
        }

        $attendanceService->checkIn($student, (int) auth()->id(), 'qr', 'QR scan');

        return redirect()->route('admin.attendance.scan')
            ->with('success', 'Student checked in successfully.');
    }

    private function findStudentForToken(Request $request, string $token): ?Student
    {
        $branchId = $request->user()->scopedBranchId();

        return Student::query()
            ->with(['branch', 'activeMembership.studySlot'])
            ->where('qr_token', $token)
            ->when($branchId !== null, fn ($query) => $query->where('branch_id', $branchId))
            ->first();
    }

    private function createChallenge(Request $request, Student $student, string $token): string
    {
        $challenge = hash('sha256', Str::random(64));
        $request->session()->put('attendance_challenges.'.$challenge, [
            'student_id' => $student->id,
            'token_hash' => hash('sha256', $token),
            'expires_at' => now()->addSeconds(self::CHALLENGE_TTL_SECONDS)->timestamp,
        ]);

        return $challenge;
    }

    private function studentForChallenge(Request $request, string $challenge): ?Student
    {
        $payload = $request->session()->get('attendance_challenges.'.$challenge);
        if (! is_array($payload) || (int) ($payload['expires_at'] ?? 0) < now()->timestamp) {
            $request->session()->forget('attendance_challenges.'.$challenge);

            return null;
        }

        $student = Student::query()
            ->with(['branch', 'activeMembership.studySlot'])
            ->find($payload['student_id'] ?? null);

        if (! $student || ! hash_equals((string) ($payload['token_hash'] ?? ''), hash('sha256', (string) $student->qr_token))) {
            $request->session()->forget('attendance_challenges.'.$challenge);

            return null;
        }

        $branchId = $request->user()->scopedBranchId();
        if ($branchId !== null && (int) $student->branch_id !== $branchId) {
            $request->session()->forget('attendance_challenges.'.$challenge);

            return null;
        }

        return $student;
    }

    private function consumeChallenge(Request $request, Student $student, string $challenge): void
    {
        $payload = $request->session()->pull('attendance_challenges.'.$challenge);

        abort_unless(is_array($payload), 403);
        abort_unless((int) ($payload['expires_at'] ?? 0) >= now()->timestamp, 403);
        abort_unless((int) ($payload['student_id'] ?? 0) === (int) $student->id, 403);
        abort_unless(
            hash_equals((string) ($payload['token_hash'] ?? ''), hash('sha256', (string) $student->qr_token)),
            403
        );

        $branchId = $request->user()->scopedBranchId();
        abort_unless($branchId === null || (int) $student->branch_id === $branchId, 403);
    }

    private function scannerResponse(
        ?Student $student = null,
        ?string $challenge = null,
        bool $lookupAttempted = false,
    ): Response {
        return response()
            ->view('admin.attendance.scan', compact('student', 'challenge', 'lookupAttempted'))
            ->header('Cache-Control', 'private, no-store, no-cache, must-revalidate')
            ->header('Pragma', 'no-cache')
            ->header('Referrer-Policy', 'no-referrer')
            ->header('X-Robots-Tag', 'noindex, nofollow, noarchive');
    }
}
