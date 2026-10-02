<?php

namespace App\Services;

use App\Models\Student;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class LibraryStudentSessionService
{
    public function claim(Student $student, string $token): void
    {
        DB::transaction(function () use ($student, $token) {
            Student::whereKey($student->id)->lockForUpdate()->firstOrFail();
            $row = DB::table('library_student_sessions')->where('student_id', $student->id)->lockForUpdate()->first();
            $hash = hash('sha256', $token);
            if ($row && $row->expires_at > now()->format('Y-m-d H:i:s') && ! hash_equals($row->token_hash, $hash)) {
                throw ValidationException::withMessages(['student_code' => 'आपका खाता दूसरे ब्राउजर में सक्रिय है। वहाँ लॉगआउट करें या ईमेल से पुराना सत्र बंद करें।']);
            }
            $values = ['token_hash' => $hash, 'expires_at' => now()->addMinutes(config('session.lifetime', 120)), 'last_seen_at' => now(), 'updated_at' => now()];
            if (! $row || ! hash_equals($row->token_hash, $hash)) {
                $values += ['practice_session_hash' => null, 'practice_expires_at' => null];
            }
            DB::table('library_student_sessions')->updateOrInsert(['student_id' => $student->id], $values + ['created_at' => $row?->created_at ?? now()]);
        }, 3);
    }

    public function touch(Student $student, ?string $token): bool
    {
        if (! $token) {
            return false;
        }

        $query = DB::table('library_student_sessions')->where('student_id', $student->id)->where('token_hash', hash('sha256', $token))->where('expires_at', '>', now());
        $updated = (clone $query)->update(['last_seen_at' => now(), 'expires_at' => now()->addMinutes(config('session.lifetime', 120)), 'updated_at' => now()]);

        // MySQL can report zero changed rows for requests within the same second.
        return $updated === 1 || $query->exists();
    }

    public function release(Student $student, ?string $token): void
    {
        if ($token) {
            DB::table('library_student_sessions')->where('student_id', $student->id)->where('token_hash', hash('sha256', $token))->delete();
        }
    }

    public function revoke(Student $student): void
    {
        DB::transaction(function () use ($student) {
            Student::whereKey($student->id)->lockForUpdate()->firstOrFail();
            DB::table('library_student_sessions')->where('student_id', $student->id)->delete();
            DB::table('mobile_api_tokens')->where('user_id', $student->user_id)->delete();
        }, 3);
    }
}
