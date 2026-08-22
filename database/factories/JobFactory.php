<?php

namespace Database\Factories;

use App\Models\Job;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<Job> */
class JobFactory extends Factory
{
    protected $model = Job::class;

    public function definition(): array
    {
        return [
            'branch_id' => null,
            'title' => fake()->jobTitle(),
            'organization' => fake()->company(),
            'job_type' => 'private',
            'category' => 'General',
            'qualification' => 'See official notification',
            'location' => fake()->city(),
            'published_date' => today(),
            'last_date' => today()->addMonth(),
            'summary' => fake()->sentence(),
            'official_url' => 'https://example.com/jobs/'.Str::lower(Str::random(10)),
            'is_featured' => false,
            'status' => true,
        ];
    }
}
