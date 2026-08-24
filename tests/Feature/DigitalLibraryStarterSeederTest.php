<?php

namespace Tests\Feature;

use App\Models\DigitalResource;
use Database\Seeders\DigitalLibraryStarterSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DigitalLibraryStarterSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_public_copyright_safe_starter_resources_idempotently(): void
    {
        $this->seed(DigitalLibraryStarterSeeder::class);
        $this->seed(DigitalLibraryStarterSeeder::class);

        $resources = DigitalResource::query()
            ->where('slug', 'like', 'starter-%')
            ->get();

        $this->assertCount(22, $resources);
        $this->assertTrue($resources->every(
            fn (DigitalResource $resource) => $resource->access_type === 'public'
                && $resource->status
                && ! $resource->download_allowed
                && $resource->file_path === null
                && str_starts_with((string) $resource->external_url, 'https://')
        ));

        foreach ([
            'Competitive Exams',
            'School & College',
            'Computer Education',
            'Language & Skills',
            'Career Materials',
            'Syllabus & Exam Pattern',
            'Notes & eBooks',
            'Video Lectures',
            'Current Affairs',
        ] as $category) {
            $this->assertTrue($resources->contains('category', $category));
        }
    }

    public function test_seeded_resources_are_visible_on_the_public_digital_library_page(): void
    {
        $this->seed(DigitalLibraryStarterSeeder::class);

        $this->get(route('digital-library.index', ['category' => 'Competitive Exams']))
            ->assertOk()
            ->assertSee('UPSC Previous Question Papers')
            ->assertSee('BPSC Official Exam Resources')
            ->assertDontSee('NCERT eBooks (Classes I–XII)');
    }
}
