<?php

namespace Tests\Feature;

use App\Models\DigitalResource;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DigitalResourceSecurityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_admin_cannot_register_private_path_outside_digital_resource_namespace(): void
    {
        $admin = User::query()->where('role', 'super_admin')->firstOrFail();

        $this->actingAs($admin)
            ->post('/admin/digital-library', [
                'title' => 'Traversal Attempt',
                'resource_type' => 'notes',
                'file_path' => '../.env',
                'access_type' => 'public',
                'download_allowed' => true,
            ])
            ->assertSessionHasErrors('resource');

        $this->assertDatabaseMissing('digital_resources', ['title' => 'Traversal Attempt']);
    }

    public function test_legacy_traversal_path_is_never_served(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('secret.txt', 'secret');

        $resource = DigitalResource::create([
            'title' => 'Unsafe Legacy Record',
            'slug' => 'unsafe-legacy-record',
            'resource_type' => 'notes',
            'file_path' => '../secret.txt',
            'access_type' => 'public',
            'download_allowed' => true,
            'status' => true,
        ]);

        $this->get('/digital-library/resources/'.$resource->id)->assertNotFound();

        $this->assertDatabaseMissing('digital_resource_logs', [
            'digital_resource_id' => $resource->id,
        ]);
    }

    public function test_unsafe_external_scheme_is_not_redirected(): void
    {
        $resource = DigitalResource::create([
            'title' => 'Unsafe External Record',
            'slug' => 'unsafe-external-record',
            'resource_type' => 'link',
            'external_url' => 'javascript:alert(1)',
            'access_type' => 'public',
            'download_allowed' => false,
            'status' => true,
        ]);

        $this->get('/digital-library/resources/'.$resource->id)->assertNotFound();
    }

    public function test_valid_namespaced_private_resource_is_served(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('digital-resources/safe-note.txt', 'safe');

        $resource = DigitalResource::create([
            'title' => 'Safe Private Resource',
            'slug' => 'safe-private-resource',
            'resource_type' => 'notes',
            'file_path' => 'digital-resources/safe-note.txt',
            'access_type' => 'public',
            'download_allowed' => true,
            'status' => true,
        ]);

        $this->get('/digital-library/resources/'.$resource->id)->assertOk();
    }
}
