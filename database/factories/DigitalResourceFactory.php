<?php

namespace Database\Factories;

use App\Models\DigitalResource;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<DigitalResource> */
class DigitalResourceFactory extends Factory
{
    protected $model = DigitalResource::class;

    public function definition(): array
    {
        $title = fake()->unique()->sentence(4);

        return [
            'branch_id' => null,
            'title' => $title,
            'slug' => Str::slug($title).'-'.Str::lower(Str::random(6)),
            'resource_type' => 'link',
            'category' => 'General',
            'description' => fake()->sentence(),
            'file_path' => null,
            'external_url' => 'https://example.com/resources/'.Str::lower(Str::random(10)),
            'access_type' => 'members',
            'download_allowed' => false,
            'status' => true,
            'uploaded_by' => null,
        ];
    }
}
