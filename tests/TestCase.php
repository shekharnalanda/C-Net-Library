<?php

namespace Tests;

use App\Models\Student;
use App\Models\User;
use App\Services\LibraryStudentSessionService;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    // Laravel 12 bootstraps the application through the framework's base test case.

    protected function libraryStudentSession(User $user): static
    {
        $student = Student::where('user_id', $user->id)->first();
        $token = 'test-device-'.$user->id;
        if ($student) {
            app(LibraryStudentSessionService::class)->claim($student, $token);
        }

        return $this->withSession(['library_device_token' => $token])->actingAs($user, 'library_student');
    }
}
