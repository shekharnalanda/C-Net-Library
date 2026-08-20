<?php

namespace App\Services;

use App\Models\CommunicationLog;
use App\Models\CommunicationTemplate;
use App\Models\Enquiry;
use App\Models\Student;
use Illuminate\Database\QueryException;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

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
        ?string $idempotencyKey = null,
    ): CommunicationLog {
        if (! $template->status) {
            throw ValidationException::withMessages([
                'template' => 'Inactive communication templates cannot be queued.',
            ]);
        }

        $recipient = trim($recipient);
        if ($recipient === '') {
            throw ValidationException::withMessages([
                'recipient' => 'A communication recipient is required.',
            ]);
        }

        $subject = $template->subject ? $this->render($template->subject, $data) : null;
        $message = $this->render($template->body, $data);

        if (($subject && $this->hasUnresolvedVariables($subject)) || $this->hasUnresolvedVariables($message)) {
            throw ValidationException::withMessages([
                'template' => 'Communication template contains unresolved variables.',
            ]);
        }

        $keyHash = $idempotencyKey !== null && trim($idempotencyKey) !== ''
            ? hash('sha256', trim($idempotencyKey))
            : null;

        if ($keyHash) {
            $existing = CommunicationLog::query()->where('idempotency_key', $keyHash)->first();
            if ($existing) {
                return $existing;
            }
        }

        try {
            return CommunicationLog::create([
                'branch_id' => $student?->branch_id ?? $enquiry?->branch_id ?? $template->branch_id,
                'student_id' => $student?->id,
                'enquiry_id' => $enquiry?->id,
                'communication_template_id' => $template->id,
                'idempotency_key' => $keyHash,
                'channel' => $template->channel,
                'recipient' => $recipient,
                'subject' => $subject,
                'message' => $message,
                'status' => 'queued',
                'created_by' => $createdBy,
            ]);
        } catch (QueryException $exception) {
            if ($keyHash) {
                $existing = CommunicationLog::query()->where('idempotency_key', $keyHash)->first();
                if ($existing) {
                    return $existing;
                }
            }

            throw $exception;
        }
    }

    public function markSent(CommunicationLog $log, ?string $provider = null, ?string $providerMessageId = null): CommunicationLog
    {
        $log->refresh();
        if ($log->status === 'sent') {
            return $log;
        }

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
        $log->refresh();
        if ($log->status === 'sent') {
            return $log;
        }

        $log->update([
            'status' => 'failed',
            'provider' => $provider,
            'failure_reason' => Str::limit(trim($reason), 2000, ''),
        ]);

        return $log->refresh();
    }

    private function hasUnresolvedVariables(string $content): bool
    {
        return preg_match('/\{[a-zA-Z0-9_\.]+\}/', $content) === 1;
    }
}
