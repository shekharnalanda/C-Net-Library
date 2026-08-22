<?php

namespace Database\Factories;

use App\Models\Branch;
use App\Models\Student;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class AttendanceFactory extends Factory
{
    public function definition(): array
    {
        $checkIn = now()->subMinutes(fake()->numberBetween(30, 240));

        return [
            'student_id' => Student::factory(),
            'branch_id' => Branch::factory(),
            'attendance_date' => today(),
            'check_in_at' => $checkIn,
            'check_out_at' => null,
            'study_minutes' => 0,
            'entry_method' => 'manual',
            'marked_by' => User::factory(),
            'remarks' => null,
        ];
    }
}
