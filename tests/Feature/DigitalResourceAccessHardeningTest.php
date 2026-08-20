<?php

namespace Tests\Feature;

use App\Models\DigitalResource;
use App\Services\DigitalResourceAccessService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DigitalResourceAccessHardeningTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_inactive_resource_is_denied_without_creating_access_log(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('digital-resources/inactive.pdf', '%PDF-1.4 inactive');

        $resource = DigitalResource::create([
            'title' => 'Inactive Resource',
            'slug' => 'inactive-resource',
            'resource_type' => 'pdf',
            'file_path' => 'digital-resources/inactive.pdf',
            'access_type' => 'public',
            'download_allowed' => true,
            'status' => false,
        ]);

        $this->get(route('digital-library.access', $resource))
            ->assertSessionHasErrors('resource');

        $this->assertDatabaseMissing('digital_resource_logs', [
            'digital_resource_id' => $resource->id,
        ]);
    }

    public function test_access_service_anonymizes_ipv4_addresses(): void
    {
        $resource = DigitalResource::create([
            'title' => 'IP Privacy Resource',
            'slug' => 'ip-privacy-resource',
            'resource_type' => 'link',
            'external_url' => 'https://example.com/resource',
            'access_type' => 'public',
            'download_allowed' => false,
            'status' => true,
        ]);

        app(DigitalResourceAccessService::class)->log($resource, 'view', null, '203.0.113.42');

        $this->assertDatabaseHas('digital_resource_logs', [
            'digital_resource_id' => $resource->id,
            'ip_address' => '203.0.113.0',
        ]);
    }

    public function test_access_service_anonymizes_ipv6_addresses(): void
    {
        $resource = DigitalResource::create([
            'title' => 'IPv6 Privacy Resource',
            'slug' => 'ipv6-privacy-resource',
            'resource_type' => 'link',
            'external_url' => 'https://example.com/resource',
            'access_type' => 'public',
            'download_allowed' => false,
            'status' => true,
        ]);

        app(DigitalResourceAccessService::class)->log($resource, 'view', null, '2001:db8:abcd:1234:5678:90ab:cdef:1234');

        $this->assertDatabaseHas('digital_resource_logs', [
            'digital_resource_id' => $resource->id,
            'ip_address' => '2001:db8:abcd:1234::',
        ]);
    }

    public function test_download_uses_safe_resource_title_filename_and_private_headers(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('digital-resources/server-generated.pdf', '%PDF-1.4 test');

        $resource = DigitalResource::create([
            'title' => 'My Exam Notes 2026',
            'slug' => 'my-exam-notes-2026',
            'resource_type' => 'pdf',
            'file_path' => 'digital-resources/server-generated.pdf',
            'access_type' => 'public',
            'download_allowed' => true,
            'status' => true,
        ]);

        $response = $this->get(route('digital-library.access', ['resource' => $resource, 'download' => 1]));

        $response->assertOk();
        $response->assertHeader('Content-Disposition', 'attachment; filename=my-exam-notes-2026.pdf');
        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $response->assertHeader('Cache-Control', 'private, no-store');
    }

    public function test_inline_view_sets_safe_content_disposition_and_private_headers(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('digital-resources/read-online.pdf', '%PDF-1.4 test');

        $resource = DigitalResource::create([
            'title' => 'Read Online',
            'slug' => 'read-online',
            'resource_type' => 'pdf',
            'file_path' => 'digital-resources/read-online.pdf',
            'access_type' => 'public',
            'download_allowed' => true,
            'status' => true,
        ]);

        $response = $this->get(route('digital-library.access', $resource));

        $response->assertOk();
        $response->assertHeader('Content-Disposition', 'inline; filename="read-online.pdf"');
        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $response->assertHeader('Cache-Control', 'private, no-store');
    }
}
