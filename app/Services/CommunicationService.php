<?php

namespace App\Services;

use App\Models\CommunicationLog;
use App\Models\CommunicationTemplate;
use App\Models\Enquiry;
use App\Models\Student;
use Illuminate\Support\Arr;

class CommunicationService
{
    public function render(string $content, array $data): string
    {
        return preg_replace_callback('/\{([a-zA-Z0-9_\.]+)\}/', function (array $matches) use ($data) {
            return (string) Arr::get($data, $matches[1], $matches[0]);
        }, $content) ?? $content;
    }

    public function queueFromTemplate(
        CommunicationTemplate $template,
        string $recipient,
        array $data = [],
        ?Student $student = null,
        ?Enquiry $enquiry = null,
        ?int $createdBy = null,
    ): CommunicationLog {
        return CommunicationLog::create([
            'branch_id' => $student?->branch_id ?? $enquiry?->branch_id ?? $template->branch_id,
            'student_id' => $student?->id,
            'enquiry_id' => $enquiry?->id,
            'communication_template_id' => $template->id,
            'channel' => $template->channel,
            'recipient' => $recipient,
            'subject' => $template->subject ? $this->render($template->subject, $data) : null,
            'message' => $this->render($template->body, $data),
            'status' => 'queued',
            'created_by' => $createdBy,
        ]);
    }

    public function markSent(CommunicationLog $log, ?string $provider = null, ?string $providerMessageId = null): CommunicationLog
    {
        $log->update([
            'status' => 'sent',
            'provider' => $provider,
            'provider_message_id' => $providerMessageId,
            'sent_at' => now(),
            'failure_reason' => null,
        ]);

        return $log->refresh();
    }

    public function markFailed(CommunicationLog $log, string $reason, ?string $provider = null): CommunicationLog
    {
        $log->update([
            'status' => 'failed',
            'provider' => $provider,
            'failure_reason' => $reason,
        ]);

        return $log->refresh();
    }
}
