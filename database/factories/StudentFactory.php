<?php

namespace Database\Factories;

use App\Models\Branch;
use App\Models\Student;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<Student> */
class StudentFactory extends Factory
{
    protected $model = Student::class;

    public function definition(): array
    {
        return [
            'branch_id' => Branch::factory(),
            'student_code' => 'CNL-'.Str::upper(Str::random(10)),
            'qr_token' => (string) Str::uuid(),
            'name' => fake()->name(),
            'mobile' => fake()->unique()->numerify('9#########'),
            'email' => fake()->unique()->safeEmail(),
            'joining_date' => today(),
            'status' => 'active',
        ];
    }
}
