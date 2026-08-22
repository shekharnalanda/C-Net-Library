<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\StudySlot;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SeatAvailabilityPageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_global_admin_can_open_available_seats_page_without_filters(): void
    {
        $admin = User::query()->where('role', 'super_admin')->firstOrFail();

        $this->actingAs($admin)
            ->get('/admin/available-seats')
            ->assertOk()
            ->assertSee('Available Seats')
            ->assertSee('Select a branch and study slot');
    }

    public function test_browser_request_renders_available_seat_results(): void
    {
        $admin = User::query()->where('role', 'super_admin')->firstOrFail();
        $branch = Branch::query()->where('status', true)->firstOrFail();
        $slot = StudySlot::query()->where('branch_id', $branch->id)->where('status', true)->firstOrFail();

        $this->actingAs($admin)
            ->get('/admin/available-seats?'.http_build_query([
                'branch_id' => $branch->id,
                'study_slot_id' => $slot->id,
                'allocated_from' => today()->toDateString(),
                'allocated_to' => today()->addDays(30)->toDateString(),
            ]))
            ->assertOk()
            ->assertSee('Check Availability')
            ->assertSee('available');
    }
}
