<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class BranchFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->company().' Branch',
            'code' => 'BR-'.Str::upper(Str::random(8)),
            'mobile' => fake()->numerify('9#########'),
            'email' => fake()->unique()->safeEmail(),
            'city' => 'Bihar Sharif',
            'state' => 'Bihar',
            'is_24x7' => false,
            'status' => true,
        ];
    }
}
