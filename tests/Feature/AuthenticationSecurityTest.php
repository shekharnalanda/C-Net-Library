<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Role;
use App\Models\Student;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthenticationSecurityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_inactive_account_cannot_authenticate(): void
    {
        $user = User::factory()->create([
            'email' => 'inactive@example.com',
            'password' => 'StrongPass1!',
            'role' => 'student',
            'status' => false,
        ]);

        $response = $this->post(route('login.store'), [
            'email' => strtoupper($user->email),
            'password' => 'StrongPass1!',
        ]);

        $response->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_deactivated_admin_session_is_invalidated_on_next_admin_request(): void
    {
        $branch = Branch::query()->where('status', true)->firstOrFail();
        $admin = User::factory()->create([
            'branch_id' => $branch->id,
            'role' => 'admin',
            'status' => true,
        ]);
        $role = Role::query()->where('slug', 'branch-admin')->firstOrFail();
        $admin->roles()->sync([$role->id]);

        $admin->update(['status' => false]);

        $response = $this->actingAs($admin)->get(route('admin.dashboard'));

        $response->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_deactivated_student_session_is_invalidated_on_next_student_request(): void
    {
        $user = User::factory()->create([
            'role' => 'student',
            'status' => true,
        ]);

        $user->update(['status' => false]);

        $response = $this->actingAs($user)->get(route('student.dashboard'));

        $response->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_student_activation_rejects_weak_password_and_keeps_token_active(): void
    {
        $branch = Branch::query()->where('status', true)->firstOrFail();
        $user = User::factory()->create([
            'role' => 'student',
            'status' => true,
        ]);
        $plainToken = 'activation-token-for-security-test';
        $student = Student::create([
            'branch_id' => $branch->id,
            'user_id' => $user->id,
            'student_code' => 'AUTH-SEC-STUDENT',
            'qr_token' => 'auth-security-qr-token',
            'portal_activation_token' => hash('sha256', $plainToken),
            'portal_activation_expires_at' => now()->addHour(),
            'name' => 'Activation Student',
            'mobile' => '9000099999',
            'joining_date' => today(),
            'status' => 'active',
        ]);

        $response = $this->post(route('student.activate.store', $plainToken), [
            'password' => 'weakpass',
            'password_confirmation' => 'weakpass',
        ]);

        $response->assertSessionHasErrors('password');
        $student->refresh();
        $this->assertNotNull($student->portal_activation_token);
        $this->assertFalse(Hash::check('weakpass', $user->fresh()->password));
    }
}
