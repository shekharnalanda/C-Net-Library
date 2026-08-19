<?php

namespace Tests\Feature;

use App\Models\CmsPage;
use App\Models\Seat;
use App\Models\SeatAllocation;
use App\Models\Student;
use App\Models\StudentMembership;
use App\Models\StudySlot;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicCmsSecurityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_homepage_reports_shift_aware_seat_slot_availability(): void
    {
        $slot = StudySlot::query()->where('status', true)->firstOrFail();
        $seat = Seat::query()->where('status', true)->firstOrFail();
        $student = Student::create([
            'branch_id' => $slot->branch_id,
            'student_code' => 'CNL-HOME-1',
            'name' => 'Homepage Test Student',
            'mobile' => '9111111111',
            'joining_date' => today(),
            'status' => 'active',
        ]);
        $plan = $slot->feePlans()->where('status', true)->firstOrFail();
        $membership = StudentMembership::create([
            'student_id' => $student->id,
            'fee_plan_id' => $plan->id,
            'study_slot_id' => $slot->id,
            'start_date' => today(),
            'expiry_date' => today()->addDays(30),
            'base_fee' => $plan->monthly_fee,
            'discount' => 0,
            'final_fee' => $plan->monthly_fee,
            'status' => 'active',
        ]);

        SeatAllocation::create([
            'student_id' => $student->id,
            'student_membership_id' => $membership->id,
            'seat_id' => $seat->id,
            'study_slot_id' => $slot->id,
            'allocated_from' => today(),
            'allocated_to' => today()->addDays(30),
            'start_time' => $slot->start_time,
            'end_time' => $slot->end_time,
            'status' => 'active',
        ]);

        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee('Live shift-wise availability');
        $response->assertSee('Seat-slots available today');
        $response->assertSee('Occupied seat-slots');
    }

    public function test_public_cms_page_outputs_escaped_seo_attributes(): void
    {
        $page = CmsPage::create([
            'slug' => 'seo-test',
            'title' => 'SEO Test',
            'excerpt' => 'SEO excerpt',
            'content' => '<p>Safe body</p>',
            'meta_title' => 'SEO <Title>',
            'meta_description' => 'Description "quoted"',
            'canonical_url' => 'https://cnetlibrary.mciedu.com/page/seo-test',
            'status' => true,
        ]);

        $response = $this->get('/page/'.$page->slug);

        $response->assertOk();
        $response->assertSee('SEO &lt;Title&gt;', false);
        $response->assertSee('Description &quot;quoted&quot;', false);
        $response->assertSee('https://cnetlibrary.mciedu.com/page/seo-test', false);
    }

    public function test_cms_update_sanitizes_executable_html_before_storage(): void
    {
        $admin = User::query()->where('role', 'super_admin')->firstOrFail();
        $page = CmsPage::query()->where('slug', 'home')->firstOrFail();

        $response = $this->actingAs($admin)->patch('/admin/cms/pages/'.$page->id, [
            'title' => $page->title,
            'excerpt' => $page->excerpt,
            'content' => '<p onclick="alert(1)">Safe <strong>content</strong></p><script>alert(1)</script><a href="javascript:alert(2)">Bad link</a>',
            'meta_title' => $page->meta_title,
            'meta_description' => $page->meta_description,
            'meta_keywords' => $page->meta_keywords,
            'canonical_url' => $page->canonical_url,
            'status' => 1,
        ]);

        $response->assertRedirect();

        $stored = $page->fresh()->content;
        $this->assertStringContainsString('<strong>content</strong>', $stored);
        $this->assertStringNotContainsString('<script', strtolower($stored));
        $this->assertStringNotContainsString('onclick=', strtolower($stored));
        $this->assertStringNotContainsString('javascript:', strtolower($stored));
    }
}
