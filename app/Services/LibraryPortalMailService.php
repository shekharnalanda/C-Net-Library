<?php

namespace App\Services;

use App\Models\Student;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

class LibraryPortalMailService
{
    public function enqueue(Student $student, string $key, string $subject, string $body): void
    {
        if (! filter_var($student->email, FILTER_VALIDATE_EMAIL)) {
            return;
        }
        DB::table('library_portal_mail')->insertOrIgnore(['student_id' => $student->id, 'event_key' => $key, 'recipient' => $student->email, 'subject' => $subject, 'body' => $body, 'status' => 'pending', 'attempts' => 0, 'created_at' => now(), 'updated_at' => now()]);
    }

    public function welcome(Student $student, bool $resend = false): void
    {
        $a = $student->seatAllocations()->with('seat.studyHall')->latest('id')->first();
        $m = $student->memberships()->with('feePlan')->latest('id')->first();
        $time = $a?->start_time ? substr($a->start_time, 0, 5).'–'.substr($a->end_time, 0, 5).' IST' : '24×7';
        $body = "नमस्कार {$student->name},\nWelcome to C-Net Library! आपका प्रवेश स्वीकृत हो गया है।\nएडमिशन नंबर: {$student->student_code}\nकैंपस: ".($student->branch?->name ?? '')."\nसीट: ".($a?->seat?->seat_no ?? '')."\nसमय: {$time}\nमासिक फीस: ₹".number_format((float) $m?->final_fee, 2)."\nलॉगिन: ".route('student.login')."\nलॉगिन आईडी आपका एडमिशन नंबर है। शुरुआती पासवर्ड आपका रजिस्टर्ड मोबाइल नंबर है; आप पैनल में इसे बदल सकते हैं।\nआपको सक्रिय सदस्यता के साथ हर महीने 10 मुफ्त प्रैक्टिस सेट मिलेंगे।\nC-Net Library · MCI Educational Group";
        $key = 'welcome:'.$student->id.($resend ? ':resend:'.bin2hex(random_bytes(8)) : '');
        $this->enqueue($student, $key, 'C-Net Library: Admission approved · '.$student->student_code, $body);
        $this->deliver($key);
    }

    public function deliver(?string $key = null): int
    {
        if (in_array(config('mail.default'), ['log', 'array'], true) && ! app()->environment('testing')) {
            return 0;
        }
        $query = DB::table('library_portal_mail')->where('status', 'pending')->where('attempts', '<', 8)->where(fn ($q) => $q->whereNull('next_attempt_at')->orWhere('next_attempt_at', '<=', now()));
        if ($key) {
            $query->where('event_key', $key);
        }
        $sent = 0;
        foreach ($query->orderBy('id')->limit(100)->pluck('id') as $id) {
            DB::transaction(function () use ($id, &$sent) {
                $row = DB::table('library_portal_mail')->where('id', $id)->lockForUpdate()->first();
                if (! $row || $row->status !== 'pending' || ($row->next_attempt_at && $row->next_attempt_at > now()->format('Y-m-d H:i:s'))) {
                    return;
                }
                if (str_starts_with($row->event_key, 'device-otp:')) {
                    $challenge = DB::table('library_device_recovery')->find((int) substr($row->event_key, 11));
                    if (! $challenge || $challenge->consumed_at || $challenge->expires_at <= now()->format('Y-m-d H:i:s')) {
                        DB::table('library_portal_mail')->where('id', $id)->update(['status' => 'expired', 'body' => '[Expired; content removed]', 'updated_at' => now()]);

                        return;
                    }
                }
                try {
                    Mail::raw($row->body, fn ($mail) => $mail->to($row->recipient)->subject($row->subject));
                    DB::table('library_portal_mail')->where('id', $id)->update(['status' => 'sent', 'sent_at' => now(), 'attempts' => $row->attempts + 1, 'body' => '[Delivered; content removed]', 'updated_at' => now(), 'error_code' => null]);
                    $sent++;
                } catch (\Throwable $e) {
                    DB::table('library_portal_mail')->where('id', $id)->update(['attempts' => $row->attempts + 1, 'next_attempt_at' => now()->addMinutes(15), 'updated_at' => now(), 'error_code' => 'MAIL_TRANSPORT_FAILED']);
                }
            }, 1);
        }

        return $sent;
    }
}
