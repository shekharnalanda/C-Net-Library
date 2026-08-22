<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\DigitalResource;
use App\Models\Job;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExternalLinkSecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_active_job_redirect_is_tracked_with_masked_ip(): void
    {
        $job = Job::create([
            'title' => 'Clerk Recruitment',
            'organization' => 'Example Board',
            'job_type' => 'government',
            'official_url' => 'https://example.org/apply',
            'last_date' => today()->addDay(),
            'status' => true,
        ]);

        $response = $this
            ->withServerVariables(['REMOTE_ADDR' => '203.0.113.77'])
            ->get(route('jobs.official', $job));

        $response->assertRedirect('https://example.org/apply');
        $response->assertHeader('Referrer-Policy', 'no-referrer');
        $this->assertDatabaseHas('job_clicks', [
            'job_id' => $job->id,
            'ip_address' => '203.0.113.0',
        ]);
    }

    public function test_inactive_or_expired_job_does_not_redirect(): void
    {
        $job = Job::create([
            'title' => 'Expired Job',
            'organization' => 'Example Board',
            'job_type' => 'government',
            'official_url' => 'https://example.org/apply',
            'last_date' => today()->subDay(),
            'status' => true,
        ]);

        $this->get(route('jobs.official', $job))->assertNotFound();
        $this->assertDatabaseCount('job_clicks', 0);
    }

    public function test_inactive_student_cannot_access_members_only_resource(): void
    {
        $branch = Branch::factory()->create(['status' => true]);
        $user = User::factory()->create(['role' => 'student', 'status' => true]);
        $student = Student::create([
            'branch_id' => $branch->id,
            'user_id' => $user->id,
            'student_code' => 'INACTIVE-STUDENT',
            'name' => 'Inactive Student',
            'mobile' => '9000000099',
            'joining_date' => today(),
            'status' => 'inactive',
        ]);
        $resource = DigitalResource::create([
            'title' => 'Members Note',
            'slug' => 'members-note',
            'resource_type' => 'notes',
            'access_type' => 'members',
            'external_url' => 'https://example.org/note',
            'download_allowed' => false,
            'status' => true,
        ]);

        $response = $this->actingAs($user)->get(route('digital-library.access', $resource));

        $response->assertSessionHasErrors('resource');
        $this->assertDatabaseCount('digital_resource_logs', 0);
    }
}
