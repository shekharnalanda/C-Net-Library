<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Http\Controllers\Controller;
use App\Models\Book;
use App\Models\DigitalResource;
use App\Models\Enquiry;
use App\Models\Job;
use App\Models\Student;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class LibraryContentController extends Controller
{
    private function student(Request $request): Student
    {
        return Student::query()
            ->where('user_id', $request->user()->id)
            ->where('status', 'active')
            ->firstOrFail();
    }

    public function books(Request $request): JsonResponse
    {
        $student = $this->student($request);
        $search = trim((string) $request->query('q', ''));

        $books = Book::query()
            ->where('status', true)
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('title', 'like', "%{$search}%")
                        ->orWhere('author', 'like', "%{$search}%")
                        ->orWhere('isbn', 'like', "%{$search}%");
                });
            })
            ->with(['category', 'copies' => fn ($q) => $q->where('branch_id', $student->branch_id)])
            ->orderBy('title')
            ->paginate(20);

        return response()->json($books);
    }

    public function issuedBooks(Request $request): JsonResponse
    {
        $student = $this->student($request);

        $issues = $student->bookIssues()
            ->with('bookCopy.book')
            ->latest('issued_at')
            ->paginate(20);

        return response()->json($issues);
    }

    public function digitalResources(Request $request): JsonResponse
    {
        $student = $this->student($request);

        $resources = DigitalResource::query()
            ->where('status', true)
            ->where(function ($query) use ($student) {
                $query->whereNull('branch_id')->orWhere('branch_id', $student->branch_id);
            })
            ->orderBy('category')
            ->orderBy('title')
            ->paginate(20);

        return response()->json($resources);
    }

    public function jobs(Request $request): JsonResponse
    {
        $student = $this->student($request);

        $jobs = Job::query()
            ->where('status', true)
            ->where(function ($query) use ($student) {
                $query->whereNull('branch_id')->orWhere('branch_id', $student->branch_id);
            })
            ->where(function ($query) {
                $query->whereNull('last_date')->orWhereDate('last_date', '>=', today());
            })
            ->orderByDesc('is_featured')
            ->orderByDesc('published_date')
            ->paginate(20);

        return response()->json($jobs);
    }

    public function qrMemberId(Request $request): JsonResponse
    {
        $student = $this->student($request);

        if (blank($student->qr_token)) {
            $student->forceFill(['qr_token' => (string) Str::uuid()])->save();
        }

        return response()->json([
            'student_code' => $student->student_code,
            'name' => $student->name,
            'qr_token' => $student->qr_token,
            'attendance_url' => route('admin.attendance.qr', ['token' => $student->qr_token]),
        ]);
    }

    public function support(Request $request): JsonResponse
    {
        $student = $this->student($request);

        $validated = $request->validate([
            'message' => ['required', 'string', 'max:2000'],
        ]);

        $enquiry = Enquiry::query()->create([
            'branch_id' => $student->branch_id,
            'enquiry_no' => 'APP-'.now()->format('YmdHis').'-'.strtoupper(Str::random(4)),
            'name' => $student->name,
            'mobile' => $student->mobile,
            'email' => $student->email,
            'source' => 'mobile_app',
            'message' => $validated['message'],
            'status' => 'new',
        ]);

        return response()->json([
            'message' => 'Support request submitted successfully.',
            'enquiry_no' => $enquiry->enquiry_no,
        ], 201);
    }
}
