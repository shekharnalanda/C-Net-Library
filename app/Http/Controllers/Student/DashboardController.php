<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\PaymentAdjustment;
use App\Models\Student;
use App\Services\QrCodeService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class DashboardController extends Controller
{
    public function index(Request $request): Response
    {
        $student = Student::query()
            ->where('user_id', $request->user()->id)
            ->with([
                'branch',
                'activeMembership.feePlan',
                'activeMembership.studySlot',
                'activeMembership.payments',
                'seatAllocations' => fn ($q) => $q->with('seat.studyHall')->latest(),
                'attendances' => fn ($q) => $q->latest('check_in_at')->limit(10),
                'bookIssues' => fn ($q) => $q->with('bookCopy.book')->latest('issued_at')->limit(10),
            ])
            ->firstOrFail();

        $membership = $student->activeMembership;
        $grossPaid = $membership ? (float) $membership->payments->whereIn('payment_status', ['paid', 'partial'])->sum('amount') : 0;
        $adjusted = $membership ? (float) PaymentAdjustment::query()
            ->whereHas('payment', fn ($query) => $query->where('student_membership_id', $membership->id))
            ->sum('amount') : 0;
        $paid = max(0, $grossPaid - $adjusted);
        $due = $membership ? max(0, (float) $membership->final_fee - $paid) : 0;
        $activeSeat = $student->seatAllocations->firstWhere('status', 'active');
        $studyMinutes = (int) $student->attendances->sum('study_minutes');

        return response()
            ->view('student.dashboard', compact('student', 'membership', 'paid', 'due', 'activeSeat', 'studyMinutes'))
            ->header('Cache-Control', 'private, no-store, no-cache, must-revalidate')
            ->header('Pragma', 'no-cache')
            ->header('X-Robots-Tag', 'noindex, nofollow,noarchive');
    }

    public function idCard(Request $request, QrCodeService $qrCode): Response
    {
        $student = Student::query()
            ->where('user_id', $request->user()->id)
            ->with(['branch', 'activeMembership.studySlot', 'activeMembership.feePlan'])
            ->firstOrFail();

        if (blank($student->qr_token)) {
            $student->forceFill(['qr_token' => (string) Str::uuid()])->save();
        }

        $scanUrl = route('admin.attendance.qr', ['token' => $student->qr_token]);
        $qrDataUri = $qrCode->svgDataUri($scanUrl);

        return response()
            ->view('student.id-card', compact('student', 'qrDataUri'))
            ->header('Cache-Control', 'private, no-store, no-cache, must-revalidate')
            ->header('Pragma', 'no-cache')
            ->header('X-Robots-Tag', 'noindex, nofollow, noarchive')
            ->header('Referrer-Policy', 'no-referrer');
    }
}
