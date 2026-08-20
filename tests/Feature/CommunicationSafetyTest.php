<?php

namespace Tests\Feature;

use App\Models\CommunicationTemplate;
use App\Services\CommunicationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class CommunicationSafetyTest extends TestCase
{
    use RefreshDatabase;

    public function test_same_idempotency_key_returns_one_communication_log(): void
    {
        $template = CommunicationTemplate::create([
            'name' => 'Fee Reminder',
            'slug' => 'fee-reminder',
            'channel' => 'sms',
            'body' => 'Hello {student.name}, fee due is {fee.due}.',
            'status' => true,
        ]);

        $service = app(CommunicationService::class);
        $data = ['student' => ['name' => 'Aman'], 'fee' => ['due' => '500']];

        $first = $service->queueFromTemplate(
            $template,
            '9000000001',
            $data,
            idempotencyKey: 'fee-reminder:student-1:2026-08'
        );
        $second = $service->queueFromTemplate(
            $template,
            '9000000001',
            $data,
            idempotencyKey: 'fee-reminder:student-1:2026-08'
        );

        $this->assertSame($first->id, $second->id);
        $this->assertDatabaseCount('communication_logs', 1);
    }

    public function test_unresolved_template_variable_is_not_queued(): void
    {
        $template = CommunicationTemplate::create([
            'name' => 'Incomplete Template',
            'slug' => 'incomplete-template',
            'channel' => 'email',
            'subject' => 'Hello {student.name}',
            'body' => 'Your balance is {fee.due}.',
            'status' => true,
        ]);

        $this->expectException(ValidationException::class);

        app(CommunicationService::class)->queueFromTemplate(
            $template,
            'student@example.com',
            ['student' => ['name' => 'Aman']]
        );
    }

    public function test_inactive_template_is_not_queued(): void
    {
        $template = CommunicationTemplate::create([
            'name' => 'Inactive',
            'slug' => 'inactive-template',
            'channel' => 'whatsapp',
            'body' => 'Hello',
            'status' => false,
        ]);

        $this->expectException(ValidationException::class);

        app(CommunicationService::class)->queueFromTemplate($template, '9000000002');
    }

    public function test_late_failure_does_not_overwrite_sent_state(): void
    {
        $template = CommunicationTemplate::create([
            'name' => 'Sent State',
            'slug' => 'sent-state',
            'channel' => 'sms',
            'body' => 'Hello',
            'status' => true,
        ]);

        $service = app(CommunicationService::class);
        $log = $service->queueFromTemplate($template, '9000000003');
        $service->markSent($log, 'example-provider', 'provider-123');
        $result = $service->markFailed($log, 'late timeout callback', 'example-provider');

        $this->assertSame('sent', $result->status);
        $this->assertSame('provider-123', $result->provider_message_id);
        $this->assertNull($result->failure_reason);
    }
}
