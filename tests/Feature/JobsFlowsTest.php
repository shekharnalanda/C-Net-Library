<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Job;
use App\Models\Student;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class JobsFlowsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_public_jobs_hide_inactive_and_expired_records(): void
    {
        Job::create([
            'title' => 'Active Job',
            'organization' => 'Org A',
            'job_type' => 'government',
            'official_url' => 'https://example.com/active',
            'last_date' => today()->addDay(),
            'status' => true,
        ]);

        Job::create([
            'title' => 'Expired Job',
            'organization' => 'Org B',
            'job_type' => 'private',
            'official_url' => 'https://example.com/expired',
            'last_date' => today()->subDay(),
            'status' => true,
        ]);

        Job::create([
            'title' => 'Inactive Job',
            'organization' => 'Org C',
            'job_type' => 'internship',
            'official_url' => 'https://example.com/inactive',
            'last_date' => today()->addDay(),
            'status' => false,
        ]);

        $this->get('/jobs')
            ->assertOk()
            ->assertSee('Active Job')
            ->assertDontSee('Expired Job')
            ->assertDontSee('Inactive Job');
    }

    public function test_student_can_save_job_idempotently_and_remove_it(): void
    {
        [$user, $student] = $this->student();

        $job = Job::create([
            'title' => 'Saved Job',
            'organization' => 'Career Org',
            'job_type' => 'government',
            'official_url' => 'https://example.com/job',
            'last_date' => today()->addWeek(),
            'status' => true,
        ]);

        $this->actingAs($user)->post(route('student.saved-jobs.store', $job))->assertRedirect();
        $this->actingAs($user)->post(route('student.saved-jobs.store', $job))->assertRedirect();

        $this->assertDatabaseCount('saved_jobs', 1);
        $this->assertDatabaseHas('saved_jobs', ['student_id' => $student->id, 'job_id' => $job->id]);

        $this->actingAs($user)->get(route('student.saved-jobs.index'))
            ->assertOk()
            ->assertSee('Saved Job');

        $this->actingAs($user)->delete(route('student.saved-jobs.destroy', $job))->assertRedirect();
        $this->assertDatabaseMissing('saved_jobs', ['student_id' => $student->id, 'job_id' => $job->id]);
    }

    public function test_student_cannot_save_expired_or_inactive_job(): void
    {
        [$user] = $this->student();

        $expired = Job::create([
            'title' => 'Expired',
            'organization' => 'Org',
            'job_type' => 'private',
            'official_url' => 'https://example.com/expired',
            'last_date' => today()->subDay(),
            'status' => true,
        ]);

        $inactive = Job::create([
            'title' => 'Inactive',
            'organization' => 'Org',
            'job_type' => 'private',
            'official_url' => 'https://example.com/inactive',
            'last_date' => today()->addDay(),
            'status' => false,
        ]);

        $this->actingAs($user)->post(route('student.saved-jobs.store', $expired))->assertNotFound();
        $this->actingAs($user)->post(route('student.saved-jobs.store', $inactive))->assertNotFound();
    }

    public function test_admin_rejects_non_http_official_job_url(): void
    {
        $admin = User::query()->where('role', 'super_admin')->firstOrFail();

        $this->actingAs($admin)->post(route('admin.jobs.store'), [
            'title' => 'Unsafe Job',
            'organization' => 'Unsafe Org',
            'job_type' => 'government',
            'official_url' => 'javascript:alert(1)',
        ])->assertSessionHasErrors('official_url');

        $this->assertDatabaseMissing('jobs', ['title' => 'Unsafe Job']);
    }

    private function student(): array
    {
        $branch = Branch::query()->where('status', true)->firstOrFail();

        $user = User::create([
            'name' => 'Jobs Student',
            'email' => 'jobs-student@example.com',
            'password' => 'password123',
            'role' => 'student',
            'status' => true,
        ]);

        $student = Student::create([
            'branch_id' => $branch->id,
            'user_id' => $user->id,
            'student_code' => 'CNL-JOBS-TEST',
            'qr_token' => 'jobs-test-token',
            'name' => 'Jobs Student',
            'mobile' => '9000000001',
            'joining_date' => today(),
            'status' => 'active',
        ]);

        return [$user, $student];
    }
}
