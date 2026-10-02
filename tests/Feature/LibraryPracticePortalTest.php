<?php

namespace Tests\Feature;

use App\Models\Student;
use App\Models\StudentMembership;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class LibraryPracticePortalTest extends TestCase
{
    use RefreshDatabase;

    public function test_library_panel_links_to_practice_and_launch_requires_membership_and_terms(): void
    {
        $this->travelTo(now()->setDate(2026, 10, 5));
        $user = User::factory()->create(['role' => 'student', 'status' => true]);
        $student = Student::factory()->create(['user_id' => $user->id, 'status' => 'active']);
        StudentMembership::factory()->create(['student_id' => $student->id, 'start_date' => '2026-10-01', 'expiry_date' => '2026-10-30', 'status' => 'active']);
        $dir = sys_get_temp_dir().'/library-launch-test-'.bin2hex(random_bytes(6));
        mkdir($dir, 0700);
        mkdir($dir.'/tickets', 0700);
        $connection = config('database.connections.'.config('database.default'));
        config(['library-practice.bridge_path' => $dir.'/config.json', 'database.connections.library_practice_source' => $connection]);
        DB::connection('library_practice_source')->setPdo(DB::connection()->getPdo());
        file_put_contents($dir.'/config.json', json_encode(['bridge_id' => str_repeat('a', 32), 'secret' => str_repeat('b', 64), 'test_url' => 'https://test.mciedu.com', 'library_connection' => $connection]));
        chmod($dir.'/config.json', 0600);
        try {
            $this->libraryStudentSession($user)->get('/student/dashboard')->assertOk()->assertSee('Online Practice');
            $this->get('/student/online-practice')->assertOk()->assertSee('अपने 10 टेस्ट सेट चुनें');
            $this->post('/student/online-practice')->assertSessionHasErrors('terms');
            $response = $this->post('/student/online-practice', ['terms' => 1])->assertRedirect();
            $this->assertStringStartsWith('https://test.mciedu.com/library-practice/enter?ticket=', $response->headers->get('Location'));
            $files = glob($dir.'/tickets/*.json');
            $this->assertCount(1, $files);
            $this->assertSame(0600, fileperms($files[0]) & 0777);
            $data = file_get_contents($files[0]);
            $this->assertStringNotContainsString($student->mobile, $data);
            $this->assertStringNotContainsString($user->email, $data);
            StudentMembership::query()->update(['status' => 'expired']);
            $this->post('/student/online-practice', ['terms' => 1])->assertSessionHasErrors('practice');
        } finally {
            DB::purge('library_practice_source');
            File::deleteDirectory($dir);
        }
    }

    public function test_unconfigured_practice_is_friendly_and_admin_cannot_issue_student_login(): void
    {
        config(['library-practice.bridge_path' => sys_get_temp_dir().'/missing-library-practice-'.bin2hex(random_bytes(6))]);
        $user = User::factory()->create(['role' => 'student', 'status' => true]);
        Student::factory()->create(['user_id' => $user->id, 'status' => 'active']);
        $this->libraryStudentSession($user)->get('/student/online-practice')->assertOk()->assertSee('सुविधा तैयार हो रही है');
        $this->post('/student/logout');
        $this->actingAs(User::factory()->create(['role' => 'super_admin', 'status' => true]), 'web')->post('/student/online-practice', ['terms' => 1])->assertRedirect(route('student.login'));
    }
}
