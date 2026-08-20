<?php

namespace Tests\Feature;

use App\Models\DigitalResource;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DigitalResourceUploadTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_admin_can_upload_pdf_to_private_local_storage(): void
    {
        Storage::fake('local');
        $admin = User::query()->where('role', 'super_admin')->firstOrFail();

        $response = $this->actingAs($admin)->post('/admin/digital-library', [
            'title' => 'Private PDF',
            'resource_type' => 'pdf',
            'resource_file' => UploadedFile::fake()->createWithContent('notes.pdf', '%PDF-1.4 test'),
            'access_type' => 'members',
            'download_allowed' => '1',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $resource = DigitalResource::query()->where('title', 'Private PDF')->firstOrFail();
        $this->assertStringStartsWith('digital-resources/', $resource->file_path);
        $this->assertNull($resource->external_url);
        Storage::disk('local')->assertExists($resource->file_path);
    }

    public function test_executable_upload_is_rejected(): void
    {
        Storage::fake('local');
        $admin = User::query()->where('role', 'super_admin')->firstOrFail();

        $this->actingAs($admin)->post('/admin/digital-library', [
            'title' => 'Bad Upload',
            'resource_type' => 'notes',
            'resource_file' => UploadedFile::fake()->create('shell.php', 10, 'application/x-php'),
            'access_type' => 'members',
        ])->assertSessionHasErrors('resource_file');

        $this->assertDatabaseMissing('digital_resources', ['title' => 'Bad Upload']);
    }

    public function test_file_and_external_url_cannot_both_be_supplied(): void
    {
        Storage::fake('local');
        $admin = User::query()->where('role', 'super_admin')->firstOrFail();

        $this->actingAs($admin)->post('/admin/digital-library', [
            'title' => 'Ambiguous Resource',
            'resource_type' => 'pdf',
            'resource_file' => UploadedFile::fake()->createWithContent('notes.pdf', '%PDF-1.4 test'),
            'external_url' => 'https://example.com/notes.pdf',
            'access_type' => 'members',
        ])->assertSessionHasErrors('resource');

        $this->assertDatabaseMissing('digital_resources', ['title' => 'Ambiguous Resource']);
    }

    public function test_link_resource_uses_http_external_url_without_local_file(): void
    {
        $admin = User::query()->where('role', 'super_admin')->firstOrFail();

        $response = $this->actingAs($admin)->post('/admin/digital-library', [
            'title' => 'Official Link',
            'resource_type' => 'link',
            'external_url' => 'https://example.com/resource',
            'access_type' => 'public',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('digital_resources', [
            'title' => 'Official Link',
            'resource_type' => 'link',
            'external_url' => 'https://example.com/resource',
            'file_path' => null,
        ]);
    }
}
