<?php

namespace Database\Factories;

use App\Models\Branch;
use App\Models\Staff;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<Staff> */
class StaffFactory extends Factory
{
    protected $model = Staff::class;

    public function definition(): array
    {
        return [
            'branch_id' => Branch::factory(),
            'user_id' => null,
            'staff_code' => 'STF-'.Str::upper(Str::random(10)),
            'name' => fake()->name(),
            'role' => 'staff',
            'mobile' => fake()->numerify('9#########'),
            'email' => fake()->unique()->safeEmail(),
            'joining_date' => today(),
            'monthly_salary' => 0,
            'status' => 'active',
        ];
    }
}
