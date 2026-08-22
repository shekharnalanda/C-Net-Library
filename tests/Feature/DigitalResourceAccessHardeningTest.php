<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\DigitalResource;
use App\Models\FeePlan;
use App\Models\Student;
use App\Models\StudentMembership;
use App\Models\StudySlot;
use App\Services\DigitalResourceAccessService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
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

    public function test_expired_membership_is_denied_for_member_resource_even_if_status_is_still_active(): void
    {
        $branch = Branch::query()->where('status', true)->firstOrFail();
        $slot = StudySlot::query()->where('branch_id', $branch->id)->where('status', true)->firstOrFail();
        $plan = FeePlan::query()->where('branch_id', $branch->id)->where('status', true)->firstOrFail();
        $student = Student::create([
            'branch_id' => $branch->id,
            'student_code' => 'DIG-EXP-'.Str::upper(Str::random(6)),
            'qr_token' => (string) Str::uuid(),
            'name' => 'Expired Digital Student',
            'mobile' => '9000000881',
            'joining_date' => today()->subMonth(),
            'status' => 'active',
        ]);
        StudentMembership::create([
            'student_id' => $student->id,
            'fee_plan_id' => $plan->id,
            'study_slot_id' => $slot->id,
            'start_date' => today()->subMonth(),
            'expiry_date' => today()->subDay(),
            'base_fee' => 1000,
            'discount' => 0,
            'final_fee' => 1000,
            'status' => 'active',
        ]);
        $resource = DigitalResource::create([
            'title' => 'Members Only Resource',
            'slug' => 'members-only-expiry-test',
            'resource_type' => 'link',
            'external_url' => 'https://example.com/member-resource',
            'access_type' => 'members',
            'download_allowed' => false,
            'status' => true,
        ]);

        try {
            app(DigitalResourceAccessService::class)->assertCanAccess($resource, $student);
            $this->fail('Expected expired membership access denial.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('resource', $exception->errors());
        }
    }

    public function test_branch_scoped_member_resource_is_denied_to_student_from_another_branch(): void
    {
        $studentBranch = Branch::query()->where('status', true)->firstOrFail();
        $otherBranch = Branch::create([
            'name' => 'Digital Branch Two',
            'code' => 'DBR2',
            'status' => true,
        ]);

        $student = Student::create([
            'branch_id' => $studentBranch->id,
            'student_code' => 'DIG-BR-'.Str::upper(Str::random(6)),
            'qr_token' => (string) Str::uuid(),
            'name' => 'Branch Digital Student',
            'mobile' => '9000000882',
            'joining_date' => today(),
            'status' => 'active',
        ]);

        $resource = DigitalResource::create([
            'branch_id' => $otherBranch->id,
            'title' => 'Other Branch Members Resource',
            'slug' => 'other-branch-members-resource',
            'resource_type' => 'link',
            'external_url' => 'https://example.com/other-branch-resource',
            'access_type' => 'members',
            'download_allowed' => false,
            'status' => true,
        ]);

        try {
            app(DigitalResourceAccessService::class)->assertCanAccess($resource, $student);
            $this->fail('Expected cross-branch digital resource access denial.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('resource', $exception->errors());
            $this->assertStringContainsString('branch', strtolower($exception->errors()['resource'][0]));
        }
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
        $cacheControl = (string) $response->headers->get('Cache-Control');
        $this->assertStringContainsString('private', $cacheControl);
        $this->assertStringContainsString('no-store', $cacheControl);
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
        $cacheControl = (string) $response->headers->get('Cache-Control');
        $this->assertStringContainsString('private', $cacheControl);
        $this->assertStringContainsString('no-store', $cacheControl);
    }
}
