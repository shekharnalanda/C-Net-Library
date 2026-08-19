<?php

namespace App\Services;

use App\Models\Attendance;
use App\Models\Student;
use Illuminate\Validation\ValidationException;

class AttendanceService
{
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

    public function checkOut(Student $student, int $markedBy, ?string $remarks = null): Attendance
    {
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
}
