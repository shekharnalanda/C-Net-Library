<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\PaymentAdjustment;
use App\Models\Student;
use App\Services\AuditService;
use App\Services\LibraryPortalMailService;
use App\Services\LibrarySpreadsheet;
use App\Services\LibraryStudentSessionService;
use App\Support\AdminBranchScope;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class LibraryStudentReportController extends Controller
{
    public function index(Request $request, LibrarySpreadsheet $excel)
    {
        $d = $request->validate(['branch_id' => ['nullable', 'integer', 'exists:branches,id'], 'student_id' => ['nullable', 'integer', 'exists:students,id'], 'status' => ['nullable', 'in:active,inactive,blocked'], 'format' => ['nullable', 'in:xlsx,pdf'], 'month' => ['nullable', 'date_format:Y-m'], 'q' => ['nullable', 'string', 'max:100']]);
        $query = AdminBranchScope::apply(Student::query(), $request)->with(['branch', 'memberships.feePlan', 'memberships.payments', 'seatAllocations.seat.studyHall']);
        if (! empty($d['branch_id'])) {
            AdminBranchScope::authorize($request, (int) $d['branch_id']);
            $query->where('branch_id', $d['branch_id']);
        }
        if (! empty($d['student_id'])) {
            AdminBranchScope::authorize($request, Student::findOrFail($d['student_id'])->branch_id);
            $query->whereKey($d['student_id']);
        }
        if (! empty($d['status'])) {
            $query->where('status', $d['status']);
        }
        if (! empty($d['q'])) {
            $q = trim($d['q']);
            $query->where(fn ($s) => $s->where('name', 'like', '%'.$q.'%')->orWhere('student_code', 'like', '%'.$q.'%'));
        }
        $query->orderBy('id');
        $format = $d['format'] ?? null;
        $students = $format ? $query->get() : $query->paginate(30)->withQueryString();
        $records = [];
        foreach ($students as $student) {
            $memberships = $student->memberships;
            if (! empty($d['month'])) {
                $start = CarbonImmutable::parse($d['month'].'-01');
                $memberships = $memberships->filter(fn ($m) => $m->start_date <= $start->endOfMonth() && $m->expiry_date >= $start);
            }
            if ($memberships->isEmpty() && ! empty($d['month'])) {
                continue;
            }
            foreach ($memberships->isEmpty() ? [null] : $memberships as $m) {
                $payments = $m ? $m->payments->whereIn('payment_status', ['paid', 'partial']) : collect();
                $adjusted = (float) PaymentAdjustment::whereIn('payment_id', $payments->pluck('id'))->sum('amount');
                $paid = max(0, (float) $payments->sum('amount') - $adjusted);
                $seat = $student->seatAllocations->where('student_membership_id', $m?->id)->sortByDesc('id')->first();
                $records[] = ['student' => $student, 'membership' => $m, 'seat' => $seat, 'paid' => $paid, 'adjusted' => $adjusted, 'due' => max(0, (float) $m?->final_fee - $paid), 'payments' => $payments->map(fn ($pay) => $pay->payment_date?->format('d-m-Y').' ₹'.$pay->amount.' '.$pay->receipt_no)->implode('; ')];
            }
        }
        if ($format === 'xlsx') {
            $rows = [['Admission No', 'Name', 'Father / Guardian', 'DOB', 'Gender', 'Mobile', 'Email', 'Address', 'Campus', 'Student Status', 'Joining Date', 'Plan', 'Period Start', 'Period End', 'Seat', 'Hall', 'Daily Time IST', 'Allocation Status', 'Fee', 'Discount', 'Final Fee', 'Net Paid', 'Refund/Adjustment', 'Due', 'Payment Records', 'Mother Name', 'Alternate Mobile', 'Guardian Name', 'Guardian Mobile', 'ID Proof Type', 'ID Proof Number']];
            foreach ($records as $r) {
                $s = $r['student'];
                $m = $r['membership'];
                $a = $r['seat'];
                $rows[] = [$s->student_code, $s->name, $s->father_name, $s->dob?->format('Y-m-d'), $s->gender, $s->mobile, $s->email, $s->address, $s->branch?->name, $s->status, $s->joining_date?->format('Y-m-d'), $m?->feePlan?->name, $m?->start_date?->format('Y-m-d'), $m?->expiry_date?->format('Y-m-d'), $a?->seat?->seat_no, $a?->seat?->studyHall?->name, $a?->start_time ? substr($a->start_time, 0, 5).'–'.substr($a->end_time, 0, 5) : ($a ? '24×7' : ''), $a?->status, $m?->base_fee, $m?->discount, $m?->final_fee, $r['paid'], $r['adjusted'], $r['due'], $r['payments'], $s->mother_name, $s->alternate_mobile, $s->guardian_name, $s->guardian_mobile, $s->id_proof_type, $s->id_proof_no];
            }

            return response($excel->create($rows))->header('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet')->header('Content-Disposition', 'attachment; filename="library-student-report.xlsx"')->header('Cache-Control', 'no-store');
        }
        $branches = AdminBranchScope::apply(Branch::query(), $request, 'id')->get();
        $mailStatus = DB::table('library_portal_mail')->whereIn('student_id', $students->pluck('id'))->orderBy('id')->get()->groupBy('student_id');

        return response()->view('admin.student-report', compact('students', 'records', 'branches', 'format', 'mailStatus'))->header('Cache-Control', 'private, no-store')->header('X-Robots-Tag', 'noindex, nofollow');
    }

    public function closeSession(Request $request, Student $student, LibraryStudentSessionService $sessions)
    {
        AdminBranchScope::authorize($request, $student->branch_id);
        $sessions->revoke($student);
        app(AuditService::class)->log('student.session_closed', $student, [], ['closed' => true], request: $request);

        return back()->with('success', 'विद्यार्थी का सक्रिय लॉगिन बंद हो गया है।');
    }

    public function resend(Request $request, Student $student, LibraryPortalMailService $mail)
    {
        AdminBranchScope::authorize($request, $student->branch_id);
        $mail->welcome($student, true);

        return back()->with('success', 'स्वागत ईमेल भेजने का अनुरोध दर्ज हुआ। नीचे भेजने की स्थिति देखें।');
    }
}
