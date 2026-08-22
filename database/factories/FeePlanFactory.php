<?php

namespace Database\Factories;

use App\Models\Branch;
use App\Models\FeePlan;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<FeePlan> */
class FeePlanFactory extends Factory
{
    protected $model = FeePlan::class;

    public function definition(): array
    {
        return [
            'branch_id' => Branch::factory(),
            'study_slot_id' => null,
            'name' => 'Plan '.Str::upper(Str::random(6)),
            'monthly_fee' => fake()->randomElement([500, 750, 1000, 1500]),
            'validity_days' => 30,
            'status' => true,
        ];
    }
}
