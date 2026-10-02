<?php

namespace App\Console\Commands;

use App\Models\PaymentAdjustment;
use App\Models\Student;
use App\Services\LibraryPortalMailService;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;

class SendLibraryFeeReminders extends Command
{
    protected $signature = 'library:fee-reminders';

    protected $description = 'Queue next-month fee reminders on the 25th and 28th in India time.';

    public function handle(LibraryPortalMailService $mail): int
    {
        $today = CarbonImmutable::now('Asia/Kolkata');
        $day = $today->day;
        if (! in_array($day, [25, 28], true)) {
            return self::SUCCESS;
        }
        $next = $today->addMonthNoOverflow()->startOfMonth();
        $end = $next->endOfMonth();
        Student::where('status', 'active')->with('branch')->chunkById(100, function ($students) use ($mail, $next, $day, $today) {
            foreach ($students as $student) {
                $current = $student->memberships()->whereDate('start_date', '<=', $today->toDateString())->whereDate('expiry_date', '>=', $today->toDateString())->where('status', 'active')->with('feePlan')->latest('id')->first();
                if (! $current) {
                    continue;
                }
                $nextMembership = $student->memberships()->whereIn('status', ['active', 'pending'])->whereDate('start_date', '<=', $next->toDateString())->whereDate('expiry_date', '>=', $next->toDateString())->with('payments')->latest('id')->first();
                $fee = $nextMembership ? (float) $nextMembership->final_fee : (float) $current->final_fee;
                $paid = 0;
                if ($nextMembership) {
                    $payments = $nextMembership->payments->whereIn('payment_status', ['paid', 'partial']);
                    $adjusted = (float) PaymentAdjustment::whereIn('payment_id', $payments->pluck('id'))->sum('amount');
                    $paid = max(0, (float) $payments->sum('amount') - $adjusted);
                }
                $due = max(0, round($fee - $paid, 2));
                if ($due <= 0) {
                    continue;
                }
                $seat = $student->seatAllocations()->whereIn('status', ['active', 'reserved'])->with('seat')->latest('id')->first();
                $text = "नमस्कार {$student->name},\n".($day === 28 ? 'दूसरा फीस रिमाइंडर' : 'फीस रिमाइंडर')."\nएडमिशन नंबर: {$student->student_code}\nअगले महीने ({$next->format('m/Y')}) की बकाया फीस: ₹".number_format($due, 2)."\nसीट: ".($seat?->seat?->seat_no ?? '—')."\nकृपया अगले महीने की 10 तारीख तक फीस जमा करें। भुगतान न होने पर 11 तारीख से सीट दूसरे विद्यार्थी के लिए उपलब्ध हो सकती है।\nयदि आपने भुगतान किया है तो लाइब्रेरी कार्यालय से रिकॉर्ड अपडेट करवाएँ।\nC-Net Library";
                $mail->enqueue($student, 'fee:'.$student->id.':'.$next->format('Y-m').':'.$day, 'C-Net Library: '.($day === 28 ? 'Second fee reminder' : 'Fee reminder'), $text);
            }
        });
        $this->info('Monthly fee reminders queued; paid next-month memberships skipped.');

        return self::SUCCESS;
    }
}
