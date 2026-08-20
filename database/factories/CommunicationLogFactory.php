<?php

namespace Database\Factories;

use App\Models\Branch;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class CommunicationLogFactory extends Factory
{
    public function definition(): array
    {
        return [
            'branch_id' => Branch::factory(),
            'student_id' => null,
            'enquiry_id' => null,
            'communication_template_id' => null,
            'channel' => 'email',
            'recipient' => fake()->unique()->safeEmail(),
            'subject' => fake()->sentence(),
            'message' => fake()->paragraph(),
            'status' => 'pending',
            'provider' => null,
            'provider_message_id' => null,
            'failure_reason' => null,
            'sent_at' => null,
            'created_by' => User::factory(),
        ];
    }
}
