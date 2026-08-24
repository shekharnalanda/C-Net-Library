<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Student;
use App\Services\AuditService;
use App\Services\QrCodeService;
use App\Support\AdminBranchScope;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class StudentController extends Controller
{
    public function index(Request $request)
    {
        $base = AdminBranchScope::apply(Student::query(), $request);

        $summary = [
            'total' => (clone $base)->count(),
            'active' => (clone $base)->where('status', 'active')->count(),
            'inactive' => (clone $base)->where('status', 'inactive')->count(),
            'blocked' => (clone $base)->where('status', 'blocked')->count(),
        ];

        $students = $base
            ->with([
                'branch',
                'activeMembership.studySlot',
                'activeMembership.feePlan',
                'seatAllocations.seat.studyHall',
            ])
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = trim($request->string('search')->toString());

                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                        ->orWhere('mobile', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('student_code', 'like', "%{$search}%");
                });
            })
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->status))
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return view('admin.students.index', compact('students', 'summary'));
    }

    public function show(Request $request, Student $student)
    {
        AdminBranchScope::authorize($request, $student->branch_id);

        $student->load([
            'branch',
            'memberships.studySlot',
            'memberships.feePlan',
            'memberships.payments.adjustments',
            'seatAllocations.seat.studyHall',
            'payments.adjustments',
        ]);

        $activeMembership = $student->memberships
            ->where('status', 'active')
            ->sortByDesc('id')
            ->first();
        $allocation = $student->seatAllocations
            ->where('status', 'active')
            ->sortByDesc('id')
            ->first();
        $openAttendance = $student->attendances()
            ->whereNull('check_out_at')
            ->latest('id')
            ->first();

        $grossPaid = $activeMembership
            ? (float) $activeMembership->payments
                ->whereIn('payment_status', ['paid', 'partial'])
                ->sum('amount')
            : 0.0;
        $adjusted = $activeMembership
            ? (float) $activeMembership->payments
                ->sum(fn ($payment) => (float) $payment->adjustments->sum('amount'))
            : 0.0;
        $paid = max(0, $grossPaid - $adjusted);
        $due = $activeMembership
            ? max(0, (float) $activeMembership->final_fee - $paid)
            : 0.0;

        return view('admin.students.show', compact(
            'student',
            'activeMembership',
            'allocation',
            'openAttendance',
            'adjusted',
            'paid',
            'due',
        ));
    }

    public function idCard(Request $request, Student $student, QrCodeService $qrCode): Response
    {
        AdminBranchScope::authorize($request, $student->branch_id);

        $student->load(['branch', 'activeMembership.studySlot']);

        if (blank($student->qr_token)) {
            $student->forceFill(['qr_token' => (string) Str::uuid()])->save();
        }

        $scanUrl = route('admin.attendance.qr', ['token' => $student->qr_token]);
        $qrDataUri = $qrCode->svgDataUri($scanUrl);
        $adminView = true;

        return response()
            ->view('student.id-card', compact('student', 'qrDataUri', 'adminView'))
            ->header('Cache-Control', 'private, no-store, no-cache, must-revalidate')
            ->header('Pragma', 'no-cache')
            ->header('X-Robots-Tag', 'noindex, nofollow, noarchive')
            ->header('Referrer-Policy', 'no-referrer');
    }

    public function rotateQr(Request $request, Student $student, AuditService $audit): RedirectResponse
    {
        AdminBranchScope::authorize($request, $student->branch_id);

        $student->update([
            'qr_token' => (string) Str::uuid(),
        ]);

        $audit->log(
            action: 'student.qr_rotated',
            auditable: $student,
            newValues: ['qr_token' => '[ROTATED]'],
            request: $request,
        );

        return back()->with('success', 'Student QR code has been rotated. The previous QR is no longer valid.');
    }
}
