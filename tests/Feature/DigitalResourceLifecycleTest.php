<?php

namespace Tests\Feature;

use App\Models\DigitalResource;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DigitalResourceLifecycleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    private function admin(): User
    {
        return User::query()->where('role', 'super_admin')->firstOrFail();
    }

    public function test_replacing_private_file_deletes_old_file_and_records_audit(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('digital-resources/old.pdf', 'old');

        $resource = DigitalResource::create([
            'title' => 'Old PDF',
            'slug' => 'old-pdf',
            'resource_type' => 'pdf',
            'file_path' => 'digital-resources/old.pdf',
            'access_type' => 'members',
            'download_allowed' => true,
            'status' => true,
        ]);

        $response = $this->actingAs($this->admin())->patch(route('admin.digital-resources.update', $resource), [
            'title' => 'Updated PDF',
            'resource_type' => 'pdf',
            'resource_file' => UploadedFile::fake()->createWithContent('new.pdf', '%PDF-1.4 test'),
            'access_type' => 'members',
            'download_allowed' => '1',
            'status' => '1',
        ]);

        $response->assertRedirect();
        Storage::disk('local')->assertMissing('digital-resources/old.pdf');

        $resource->refresh();
        $this->assertNotSame('digital-resources/old.pdf', $resource->file_path);
        Storage::disk('local')->assertExists($resource->file_path);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'digital-resource.updated',
            'auditable_type' => DigitalResource::class,
            'auditable_id' => $resource->id,
        ]);
    }

    public function test_switching_to_external_url_deletes_old_private_file(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('digital-resources/old.pdf', 'old');

        $resource = DigitalResource::create([
            'title' => 'Old PDF',
            'slug' => 'old-pdf',
            'resource_type' => 'pdf',
            'file_path' => 'digital-resources/old.pdf',
            'access_type' => 'public',
            'download_allowed' => true,
            'status' => true,
        ]);

        $this->actingAs($this->admin())->patch(route('admin.digital-resources.update', $resource), [
            'title' => 'External PDF',
            'resource_type' => 'pdf',
            'external_url' => 'https://example.com/resource.pdf',
            'access_type' => 'public',
            'download_allowed' => '1',
            'status' => '1',
        ])->assertRedirect();

        Storage::disk('local')->assertMissing('digital-resources/old.pdf');
        $resource->refresh();
        $this->assertNull($resource->file_path);
        $this->assertSame('https://example.com/resource.pdf', $resource->external_url);
    }

    public function test_delete_removes_private_file_and_records_audit(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('digital-resources/delete-me.pdf', 'file');

        $resource = DigitalResource::create([
            'title' => 'Delete Me',
            'slug' => 'delete-me',
            'resource_type' => 'pdf',
            'file_path' => 'digital-resources/delete-me.pdf',
            'access_type' => 'members',
            'download_allowed' => true,
            'status' => true,
        ]);

        $id = $resource->id;

        $this->actingAs($this->admin())
            ->delete(route('admin.digital-resources.destroy', $resource))
            ->assertRedirect();

        Storage::disk('local')->assertMissing('digital-resources/delete-me.pdf');
        $this->assertDatabaseMissing('digital_resources', ['id' => $id]);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'digital-resource.deleted',
            'auditable_type' => DigitalResource::class,
            'auditable_id' => $id,
        ]);
    }

    public function test_delete_does_not_touch_legacy_path_outside_private_namespace(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('sensitive.txt', 'do not delete');

        $resource = DigitalResource::create([
            'title' => 'Legacy Bad Path',
            'slug' => 'legacy-bad-path',
            'resource_type' => 'notes',
            'file_path' => '../sensitive.txt',
            'access_type' => 'public',
            'download_allowed' => false,
            'status' => true,
        ]);

        $this->actingAs($this->admin())
            ->delete(route('admin.digital-resources.destroy', $resource))
            ->assertRedirect();

        Storage::disk('local')->assertExists('sensitive.txt');
    }
}
