<?php

namespace App\Services;

use App\Models\Attendance;
use App\Models\Student;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AttendanceService
{
    public function __construct(private readonly SettingsService $settings)
    {
    }

    public function checkIn(Student $student, int $markedBy, string $entryMethod = 'manual', ?string $remarks = null): Attendance
    {
        return DB::transaction(function () use ($student, $markedBy, $entryMethod, $remarks) {
            $lockedStudent = Student::query()->whereKey($student->id)->lockForUpdate()->firstOrFail();

            if ($lockedStudent->status !== 'active') {
                throw ValidationException::withMessages([
                    'student' => 'Inactive students cannot check in.',
                ]);
            }

            $membership = $lockedStudent->memberships()
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
                $this->assertQrCooldown($lockedStudent);
            }

            $openSession = Attendance::query()
                ->where('student_id', $lockedStudent->id)
                ->whereNull('check_out_at')
                ->latest('id')
                ->lockForUpdate()
                ->first();

            if ($openSession) {
                throw ValidationException::withMessages([
                    'student' => 'Student is already checked in.',
                ]);
            }

            return Attendance::create([
                'student_id' => $lockedStudent->id,
                'branch_id' => $lockedStudent->branch_id,
                'attendance_date' => today()->toDateString(),
                'check_in_at' => now(),
                'entry_method' => $entryMethod,
                'marked_by' => $markedBy,
                'remarks' => $remarks,
            ]);
        }, 3);
    }

    public function checkOut(Student $student, int $markedBy, ?string $remarks = null, string $entryMethod = 'manual'): Attendance
    {
        return DB::transaction(function () use ($student, $markedBy, $remarks, $entryMethod) {
            $lockedStudent = Student::query()->whereKey($student->id)->lockForUpdate()->firstOrFail();

            if ($entryMethod === 'qr') {
                $this->assertQrCooldown($lockedStudent);
            }

            $attendance = Attendance::query()
                ->where('student_id', $lockedStudent->id)
                ->whereNull('check_out_at')
                ->latest('id')
                ->lockForUpdate()
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
        }, 3);
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
