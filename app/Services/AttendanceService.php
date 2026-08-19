<?php

namespace App\Services;

use App\Models\Attendance;
use App\Models\Student;
use Illuminate\Validation\ValidationException;

class AttendanceService
{
    public function __construct(private readonly SettingsService $settings)
    {
    }

    public function checkIn(Student $student, int $markedBy, string $entryMethod = 'manual', ?string $remarks = null): Attendance
    {
        $membership = $student->memberships()
            ->where('status', 'active')
            ->whereDate('start_date', '<=', today())
            ->whereDate('expiry_date', '>=', today())
            ->latest('id')
            ->first();

        if (! $membership) {
            throw ValidationException::withMessages([
                'student' => 'Student does not have an active membership today.',
            ]);
        }

        if ($entryMethod === 'qr') {
            $this->assertQrCooldown($student);
        }

        $openSession = Attendance::query()
            ->where('student_id', $student->id)
            ->whereNull('check_out_at')
            ->latest('id')
            ->first();

        if ($openSession) {
            throw ValidationException::withMessages([
                'student' => 'Student is already checked in.',
            ]);
        }

        return Attendance::create([
            'student_id' => $student->id,
            'branch_id' => $student->branch_id,
            'attendance_date' => today()->toDateString(),
            'check_in_at' => now(),
            'entry_method' => $entryMethod,
            'marked_by' => $markedBy,
            'remarks' => $remarks,
        ]);
    }

    public function checkOut(Student $student, int $markedBy, ?string $remarks = null, string $entryMethod = 'manual'): Attendance
    {
        if ($entryMethod === 'qr') {
            $this->assertQrCooldown($student);
        }

        $attendance = Attendance::query()
            ->where('student_id', $student->id)
            ->whereNull('check_out_at')
            ->latest('id')
            ->first();

        if (! $attendance) {
            throw ValidationException::withMessages([
                'student' => 'No open attendance session was found for this student.',
            ]);
        }

        $checkout = now();
        $minutes = max(0, $attendance->check_in_at->diffInMinutes($checkout));

        $attendance->update([
            'check_out_at' => $checkout,
            'study_minutes' => $minutes,
            'marked_by' => $markedBy,
            'remarks' => $remarks ?: $attendance->remarks,
        ]);

        return $attendance->fresh();
    }

    private function assertQrCooldown(Student $student): void
    {
        $seconds = max(0, (int) $this->settings->get('qr_cooldown_seconds', 30, $student->branch_id));
        if ($seconds === 0) {
            return;
        }

        $recent = Attendance::query()
            ->where('student_id', $student->id)
            ->where('entry_method', 'qr')
            ->where(function ($query) use ($seconds) {
                $cutoff = now()->subSeconds($seconds);
                $query->where('check_in_at', '>=', $cutoff)
                    ->orWhere('check_out_at', '>=', $cutoff);
            })
            ->exists();

        if ($recent) {
            throw ValidationException::withMessages([
                'student' => "Please wait {$seconds} seconds before scanning this QR again.",
            ]);
        }
    }
}
