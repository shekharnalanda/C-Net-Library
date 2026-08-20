<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Job;
use App\Models\JobClick;
use App\Models\Student;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class JobRedirectController extends Controller
{
    public function __invoke(Request $request, Job $job): RedirectResponse
    {
        abort_unless($job->status, 404);
        abort_if($job->last_date && $job->last_date->lt(today()), 404);

        $scheme = strtolower((string) parse_url($job->official_url, PHP_URL_SCHEME));
        abort_unless(in_array($scheme, ['http', 'https'], true), 404);

        $student = null;
        if ($request->user()?->role === 'student') {
            $student = Student::query()->where('user_id', $request->user()->id)->first();
        }

        JobClick::create([
            'job_id' => $job->id,
            'student_id' => $student?->id,
            'ip_address' => $this->maskIp($request->ip()),
            'user_agent' => mb_substr((string) $request->userAgent(), 0, 500),
        ]);

        return redirect()
            ->away($job->official_url)
            ->withHeaders([
                'Referrer-Policy' => 'no-referrer',
                'X-Robots-Tag' => 'noindex, nofollow, noarchive',
                'Cache-Control' => 'private, no-store',
            ]);
    }

    private function maskIp(?string $ip): ?string
    {
        if (! $ip) {
            return null;
        }

        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            $parts = explode('.', $ip);
            $parts[3] = '0';

            return implode('.', $parts);
        }

        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            $packed = @inet_pton($ip);
            if ($packed !== false) {
                $masked = substr($packed, 0, 8).str_repeat("\0", 8);

                return inet_ntop($masked) ?: null;
            }
        }

        return null;
    }
}
