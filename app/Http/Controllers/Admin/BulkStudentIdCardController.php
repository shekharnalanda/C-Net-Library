<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Student;
use App\Services\QrCodeService;
use App\Support\AdminBranchScope;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class BulkStudentIdCardController extends Controller
{
    public function index(Request $request): Response
    {
        $search = trim($request->string('search')->toString());

        $students = AdminBranchScope::apply(Student::query(), $request)
            ->with(['branch', 'activeMembership.studySlot', 'activeMembership.feePlan'])
            ->where('status', 'active')
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($studentQuery) use ($search) {
                    $studentQuery->where('name', 'like', '%'.$search.'%')
                        ->orWhere('student_code', 'like', '%'.$search.'%')
                        ->orWhere('mobile', 'like', '%'.$search.'%');
                });
            })
            ->orderBy('name')
            ->limit(100)
            ->get();

        return response()
            ->view('admin.students.bulk-id-cards', compact('students', 'search'))
            ->header('Cache-Control', 'private, no-store, no-cache, must-revalidate')
            ->header('Pragma', 'no-cache')
            ->header('X-Robots-Tag', 'noindex, nofollow, noarchive');
    }

    public function print(Request $request, QrCodeService $qrCode): Response
    {
        $validated = $request->validate([
            'students' => ['required', 'array', 'min:1', 'max:100'],
            'students.*' => ['required', 'integer', 'distinct', 'exists:students,id'],
        ]);

        $selectedIds = array_map('intval', array_values($validated['students']));

        $found = AdminBranchScope::apply(Student::query(), $request)
            ->with(['branch', 'activeMembership.studySlot', 'activeMembership.feePlan'])
            ->where('status', 'active')
            ->whereIn('id', $selectedIds)
            ->get()
            ->keyBy('id');

        abort_unless(
            $found->count() === count($selectedIds),
            422,
            'One or more selected students are inactive or outside your permitted branch.'
        );

        $students = collect($selectedIds)->map(function (int $id) use ($found) {
            $student = $found->get($id);

            if (blank($student->qr_token)) {
                $student->forceFill(['qr_token' => (string) Str::uuid()])->save();
            }

            return $student;
        });

        $qrDataUris = $students->mapWithKeys(function (Student $student) use ($qrCode) {
            $scanUrl = route('admin.attendance.qr', ['token' => $student->qr_token]);

            return [$student->id => $qrCode->svgDataUri($scanUrl, 160)];
        });

        return response()
            ->view('admin.students.bulk-id-cards-print', compact('students', 'qrDataUris'))
            ->header('Cache-Control', 'private, no-store, no-cache, must-revalidate')
            ->header('Pragma', 'no-cache')
            ->header('X-Robots-Tag', 'noindex, nofollow, noarchive')
            ->header('Referrer-Policy', 'no-referrer');
    }
}
