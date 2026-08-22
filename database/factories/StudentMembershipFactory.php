<?php

namespace Database\Factories;

use App\Models\FeePlan;
use App\Models\Student;
use App\Models\StudentMembership;
use App\Models\StudySlot;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<StudentMembership> */
class StudentMembershipFactory extends Factory
{
    protected $model = StudentMembership::class;

    public function definition(): array
    {
        return [
            'student_id' => Student::factory(),
            'fee_plan_id' => FeePlan::factory(),
            'study_slot_id' => StudySlot::factory(),
            'start_date' => today(),
            'expiry_date' => today()->addDays(29),
            'base_fee' => 1000,
            'discount' => 0,
            'final_fee' => 1000,
            'status' => 'active',
        ];
    }
}
