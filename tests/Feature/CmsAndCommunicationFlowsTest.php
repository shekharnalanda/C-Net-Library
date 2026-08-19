<?php

namespace Tests\Feature;

use App\Models\CommunicationTemplate;
use App\Models\GalleryItem;
use App\Models\Setting;
use App\Models\User;
use App\Services\CommunicationService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CmsAndCommunicationFlowsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_admin_can_upload_and_delete_gallery_image(): void
    {
        Storage::fake('public');
        $admin = User::query()->where('role', 'super_admin')->firstOrFail();

        $response = $this->actingAs($admin)->post('/admin/cms/gallery', [
            'title' => 'Reading Hall',
            'alt_text' => 'Students studying in the reading hall',
            'image' => UploadedFile::fake()->image('hall.jpg', 800, 600),
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $item = GalleryItem::query()->where('title', 'Reading Hall')->firstOrFail();
        Storage::disk('public')->assertExists($item->image_path);

        $delete = $this->actingAs($admin)->delete('/admin/cms/gallery/'.$item->id);
        $delete->assertRedirect();
        Storage::disk('public')->assertMissing($item->image_path);
        $this->assertDatabaseMissing('gallery_items', ['id' => $item->id]);
    }

    public function test_gallery_rejects_non_image_upload(): void
    {
        Storage::fake('public');
        $admin = User::query()->where('role', 'super_admin')->firstOrFail();

        $this->actingAs($admin)
            ->post('/admin/cms/gallery', [
                'title' => 'Invalid Upload',
                'image' => UploadedFile::fake()->create('payload.txt', 20, 'text/plain'),
            ])
            ->assertSessionHasErrors('image');

        $this->assertDatabaseMissing('gallery_items', ['title' => 'Invalid Upload']);
    }

    public function test_homepage_renders_public_contact_settings(): void
    {
        Setting::query()->where('key', 'support_phone')->update(['value' => '9876543210']);
        Setting::query()->where('key', 'support_email')->update(['value' => 'help@example.com']);

        $this->get('/')
            ->assertOk()
            ->assertSee('9876543210')
            ->assertSee('help@example.com');
    }

    public function test_settings_rejects_non_google_map_embed_url(): void
    {
        $admin = User::query()->where('role', 'super_admin')->firstOrFail();
        $map = Setting::query()->where('key', 'map_embed_url')->firstOrFail();

        $this->actingAs($admin)
            ->patch('/admin/settings', [
                'settings' => [
                    $map->id => 'https://example.com/malicious-embed',
                ],
            ])
            ->assertSessionHasErrors('settings.'.$map->id);

        $this->assertNotSame('https://example.com/malicious-embed', $map->fresh()->value);
    }

    public function test_json_setting_is_normalized_from_comma_separated_text(): void
    {
        $admin = User::query()->where('role', 'super_admin')->firstOrFail();
        $setting = Setting::query()->where('type', 'json')->firstOrFail();

        $this->actingAs($admin)
            ->patch('/admin/settings', [
                'settings' => [
                    $setting->id => 'cash, upi, card',
                ],
            ])
            ->assertRedirect();

        $this->assertSame(['cash', 'upi', 'card'], json_decode($setting->fresh()->value, true));
    }

    public function test_communication_service_renders_and_tracks_log_state(): void
    {
        $admin = User::query()->where('role', 'super_admin')->firstOrFail();
        $template = CommunicationTemplate::create([
            'name' => 'Test Reminder',
            'slug' => 'test-reminder',
            'channel' => 'sms',
            'subject' => null,
            'body' => 'Hello {student.name}, your seat is {seat.no}.',
            'status' => true,
        ]);

        $service = app(CommunicationService::class);
        $log = $service->queueFromTemplate(
            $template,
            '9999999999',
            ['student' => ['name' => 'Ravi'], 'seat' => ['no' => 'A-01']],
            createdBy: $admin->id,
        );

        $this->assertSame('queued', $log->status);
        $this->assertSame('Hello Ravi, your seat is A-01.', $log->message);

        $sent = $service->markSent($log, 'test-provider', 'msg-123');
        $this->assertSame('sent', $sent->status);
        $this->assertSame('test-provider', $sent->provider);
        $this->assertSame('msg-123', $sent->provider_message_id);
        $this->assertNotNull($sent->sent_at);
    }
}
