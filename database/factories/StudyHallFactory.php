<?php

namespace Database\Factories;

use App\Models\Branch;
use Illuminate\Database\Eloquent\Factories\Factory;

class StudyHallFactory extends Factory
{
    public function definition(): array
    {
        return [
            'branch_id' => Branch::factory(),
            'name' => fake()->unique()->words(2, true).' Hall',
            'floor' => fake()->randomElement(['Ground', 'First', 'Second']),
            'total_seats' => fake()->numberBetween(20, 100),
            'status' => true,
        ];
    }
}
