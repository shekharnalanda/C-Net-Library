<?php

namespace Tests\Feature;

use App\Models\Admission;
use App\Models\Attendance;
use App\Models\BookIssue;
use App\Models\Payment;
use App\Models\SeatAllocation;
use App\Models\Student;
use App\Models\StudentMembership;
use Database\Seeders\DemoLibraryDataSeeder;
use Database\Seeders\RemoveDemoLibraryDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DemoLibraryDataSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_demo_data_is_complete_idempotent_and_removable(): void
    {
        $this->seed(DemoLibraryDataSeeder::class);
        $this->seed(DemoLibraryDataSeeder::class);

        $students = Student::where('student_code', 'like', 'DEMO-STU-%')->get();

        $this->assertCount(3, $students);
        $this->assertTrue($students->every(fn (Student $student) => str_starts_with($student->name, '[DEMO]')));
        $this->assertDatabaseCount('admissions', 3);
        $this->assertSame(3, StudentMembership::whereIn('student_id', $students->pluck('id'))->count());
        $this->assertSame(3, SeatAllocation::whereIn('student_id', $students->pluck('id'))->count());
        $this->assertSame(3, Payment::whereIn('student_id', $students->pluck('id'))->count());
        $this->assertSame(9, Attendance::whereIn('student_id', $students->pluck('id'))->count());
        $this->assertSame(3, BookIssue::whereIn('student_id', $students->pluck('id'))->count());

        $this->seed(RemoveDemoLibraryDataSeeder::class);

        $this->assertSame(0, Student::where('student_code', 'like', 'DEMO-STU-%')->count());
        $this->assertSame(0, Admission::where('application_no', 'like', 'DEMO-ADM-%')->count());
        $this->assertDatabaseMissing('books', ['isbn' => 'DEMO-BOOK-001']);
    }
}
