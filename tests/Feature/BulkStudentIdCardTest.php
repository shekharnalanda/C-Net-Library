<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Student;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BulkStudentIdCardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_admin_can_select_and_print_two_student_id_cards_on_one_a4_sheet(): void
    {
        $admin = User::query()->where('role', 'super_admin')->firstOrFail();
        $branch = Branch::query()->where('status', true)->firstOrFail();
        $students = Student::factory()->count(2)->create([
            'branch_id' => $branch->id,
            'status' => 'active',
        ]);

        $index = $this->actingAs($admin)->get(route('admin.students.id-cards.bulk'));

        $index->assertOk()
            ->assertSee('Bulk Student ID Cards')
            ->assertSee($students[0]->student_code)
            ->assertSee($students[1]->student_code);

        $response = $this->actingAs($admin)->post(route('admin.students.id-cards.bulk.print'), [
            'students' => $students->pluck('id')->all(),
        ]);

        $response->assertOk()
            ->assertSee('2 students per sheet')
            ->assertSee($students[0]->name)
            ->assertSee($students[1]->name)
            ->assertSee('grid-template-rows:128mm 128mm', false)
            ->assertSee('grid-template-columns:85.6mm 85.6mm', false);

        $response->assertHeader('X-Robots-Tag', 'noindex, nofollow, noarchive');
        $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));

        foreach ($students as $student) {
            $student->refresh();
            $this->assertNotNull($student->qr_token);
            $response->assertDontSee($student->qr_token, false);
        }
    }

    public function test_bulk_print_requires_at_least_one_student(): void
    {
        $admin = User::query()->where('role', 'super_admin')->firstOrFail();

        $this->actingAs($admin)
            ->from(route('admin.students.id-cards.bulk'))
            ->post(route('admin.students.id-cards.bulk.print'), [])
            ->assertRedirect(route('admin.students.id-cards.bulk'))
            ->assertSessionHasErrors('students');
    }
}
