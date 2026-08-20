<?php

namespace App\Services;

use App\Models\DigitalResource;
use App\Models\DigitalResourceLog;
use App\Models\Student;
use Illuminate\Validation\ValidationException;

class DigitalResourceAccessService
{
    public function assertCanAccess(DigitalResource $resource, ?Student $student = null): void
    {
        if (! $resource->status) {
            throw ValidationException::withMessages(['resource' => 'This resource is not available.']);
        }

        if ($resource->access_type === 'public') {
            return;
        }

        if (! $student) {
            throw ValidationException::withMessages(['resource' => 'Student login is required for this resource.']);
        }

        $membership = $student->memberships()
            ->where('status', 'active')
            ->whereDate('start_date', '<=', today())
            ->whereDate('expiry_date', '>=', today())
            ->latest('id')
            ->first();

        if (! $membership) {
            throw ValidationException::withMessages(['resource' => 'An active membership is required.']);
        }

        if ($resource->access_type === 'premium' && ! str_contains(strtolower($membership->feePlan?->name ?? ''), 'premium')) {
            throw ValidationException::withMessages(['resource' => 'This resource requires a premium membership.']);
        }
    }

    public function log(DigitalResource $resource, string $action, ?Student $student = null, ?string $ipAddress = null): void
    {
        DigitalResourceLog::create([
            'digital_resource_id' => $resource->id,
            'student_id' => $student?->id,
            'action' => $action,
            'accessed_at' => now(),
            'ip_address' => $this->anonymizeIp($ipAddress),
        ]);
    }

    private function anonymizeIp(?string $ipAddress): ?string
    {
        if (! $ipAddress || filter_var($ipAddress, FILTER_VALIDATE_IP) === false) {
            return null;
        }

        if (filter_var($ipAddress, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            $parts = explode('.', $ipAddress);
            $parts[3] = '0';

            return implode('.', $parts);
        }

        $packed = @inet_pton($ipAddress);
        if ($packed === false) {
            return null;
        }

        return inet_ntop(substr($packed, 0, 8).str_repeat("\0", 8)) ?: null;
    }
}
