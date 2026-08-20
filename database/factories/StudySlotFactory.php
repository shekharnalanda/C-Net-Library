<?php

namespace Database\Factories;

use App\Models\Branch;
use Illuminate\Database\Eloquent\Factories\Factory;

class StudySlotFactory extends Factory
{
    public function definition(): array
    {
        return [
            'branch_id' => Branch::factory(),
            'name' => fake()->unique()->randomElement(['Morning', 'Afternoon', 'Evening', 'Night']).' '.fake()->numberBetween(1, 99),
            'duration_hours' => 6,
            'start_time' => '06:00:00',
            'end_time' => '12:00:00',
            'is_24x7' => false,
            'is_flexible' => false,
            'status' => true,
        ];
    }
}
