<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Admission;
use App\Models\Branch;
use App\Models\Enquiry;
use App\Models\Student;
use App\Models\User;
use App\Services\AuditService;
use App\Support\AdminBranchScope;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class EnquiryController extends Controller
{
    public function index(Request $request): View
    {
        $branchId = AdminBranchScope::id($request);
        $baseQuery = AdminBranchScope::apply(Enquiry::query(), $request);

        $summary = [
            'total' => (clone $baseQuery)->count(),
            'new' => (clone $baseQuery)->where('status', 'new')->count(),
            'follow_up' => (clone $baseQuery)->where('status', 'follow_up')->count(),
            'qualified' => (clone $baseQuery)->where('status', 'qualified')->count(),
            'converted' => (clone $baseQuery)->where('status', 'converted')->count(),
            'overdue_follow_up' => (clone $baseQuery)
                ->whereNotIn('status', ['converted', 'lost'])
                ->whereNotNull('follow_up_date')
                ->whereDate('follow_up_date', '<', today())
                ->count(),
            'due_today' => (clone $baseQuery)
                ->whereNotIn('status', ['converted', 'lost'])
                ->whereDate('follow_up_date', today())
                ->count(),
        ];

        $enquiries = (clone $baseQuery)
            ->with(['branch', 'assignee', 'convertedAdmission'])
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')))
            ->when($request->filled('assigned_to'), fn ($query) => $query->where('assigned_to', $request->integer('assigned_to')))
            ->when($request->filled('follow_up'), function ($query) use ($request) {
                match ((string) $request->string('follow_up')) {
                    'overdue' => $query->whereNotIn('status', ['converted', 'lost'])->whereNotNull('follow_up_date')->whereDate('follow_up_date', '<', today()),
                    'today' => $query->whereNotIn('status', ['converted', 'lost'])->whereDate('follow_up_date', today()),
                    'upcoming' => $query->whereNotIn('status', ['converted', 'lost'])->whereDate('follow_up_date', '>', today()),
                    'unassigned' => $query->whereNull('assigned_to'),
                    default => $query,
                };
            })
            ->when($request->filled('q'), function ($query) use ($request) {
                $term = '%'.$request->string('q').'%';
                $query->where(function ($sub) use ($term) {
                    $sub->where('name', 'like', $term)
                        ->orWhere('mobile', 'like', $term)
                        ->orWhere('email', 'like', $term)
                        ->orWhere('enquiry_no', 'like', $term);
                });
            })
            ->orderByRaw("CASE WHEN status NOT IN ('converted','lost') AND follow_up_date IS NOT NULL AND follow_up_date < ? THEN 0 WHEN status NOT IN ('converted','lost') AND follow_up_date = ? THEN 1 ELSE 2 END", [today()->toDateString(), today()->toDateString()])
            ->latest()
            ->paginate(25)
            ->withQueryString();

        $staff = User::query()
            ->whereIn('role', ['super_admin', 'branch_admin', 'counselor', 'admin'])
            ->when($branchId !== null, fn ($query) => $query->where('branch_id', $branchId))
            ->orderBy('name')
            ->get();

        return view('admin.enquiries.index', [
            'enquiries' => $enquiries,
            'summary' => $summary,
            'branches' => $branchId === null
                ? Branch::query()->where('status', true)->orderBy('name')->get()
                : Branch::query()->whereKey($branchId)->get(),
            'staff' => $staff,
        ]);
    }

    public function update(Request $request, Enquiry $enquiry, AuditService $audit): RedirectResponse
    {
        if (in_array($enquiry->status, ['converted', 'lost'], true)) {
            throw ValidationException::withMessages([
                'status' => 'Converted or lost enquiries are terminal records and cannot be reopened or edited.',
            ]);
        }

        $data = $request->validate([
            'status' => ['required', 'in:new,contacted,follow_up,qualified,lost'],
            'assigned_to' => ['nullable', 'exists:users,id'],
            'follow_up_date' => ['nullable', 'date'],
            'follow_up_notes' => ['nullable', 'string', 'max:3000'],
        ]);

        if (! empty($data['assigned_to'])) {
            $assignee = User::query()->whereKey($data['assigned_to'])->firstOrFail();
            $branchId = $enquiry->branch_id;

            if ($branchId !== null && ! $assignee->isGlobalAdmin()) {
                abort_unless((int) $assignee->branch_id === (int) $branchId, 403);
            }

            if (! $request->user()->isGlobalAdmin()) {
                abort_unless((int) $assignee->branch_id === (int) $request->user()->branch_id, 403);
            }
        }

        if ($data['status'] === 'follow_up' && empty($data['follow_up_date'])) {
            throw ValidationException::withMessages([
                'follow_up_date' => 'A follow-up date is required when the enquiry is in follow-up status.',
            ]);
        }

        if ($data['status'] === 'lost' && trim((string) ($data['follow_up_notes'] ?? '')) === '') {
            throw ValidationException::withMessages([
                'follow_up_notes' => 'A reason or note is required before marking an enquiry as lost.',
            ]);
        }

        $old = $enquiry->only(['status', 'assigned_to', 'follow_up_date', 'follow_up_notes']);
        $enquiry->update($data);
        $enquiry->refresh();

        $audit->log('enquiry.updated', $enquiry, $old, $enquiry->only([
            'status', 'assigned_to', 'follow_up_date', 'follow_up_notes',
        ]));

        return back()->with('success', 'Enquiry updated.');
    }

    public function convert(Enquiry $enquiry, AuditService $audit): RedirectResponse
    {
        $old = $enquiry->only(['status', 'converted_admission_id']);

        $admission = DB::transaction(function () use ($enquiry) {
            $lockedEnquiry = Enquiry::query()->whereKey($enquiry->id)->lockForUpdate()->firstOrFail();

            if ($lockedEnquiry->converted_admission_id) {
                return Admission::query()->findOrFail($lockedEnquiry->converted_admission_id);
            }

            if ($lockedEnquiry->status === 'lost') {
                throw ValidationException::withMessages([
                    'enquiry' => 'A lost enquiry cannot be converted. Review and create a new enquiry if the prospect returns.',
                ]);
            }

            $duplicateStudent = Student::query()
                ->where('mobile', $lockedEnquiry->mobile)
                ->when($lockedEnquiry->branch_id !== null, fn ($query) => $query->where('branch_id', $lockedEnquiry->branch_id))
                ->where('status', 'active')
                ->exists();

            if ($duplicateStudent) {
                throw ValidationException::withMessages(['enquiry' => 'An active student with this mobile number already exists in this branch.']);
            }

            $duplicateAdmission = Admission::query()
                ->where('mobile', $lockedEnquiry->mobile)
                ->when($lockedEnquiry->branch_id !== null, fn ($query) => $query->where('branch_id', $lockedEnquiry->branch_id))
                ->whereIn('status', ['new', 'under_review', 'approved', 'converted'])
                ->exists();

            if ($duplicateAdmission) {
                throw ValidationException::withMessages(['enquiry' => 'An active admission record with this mobile number already exists in this branch.']);
            }

            $admission = Admission::create([
                'branch_id' => $lockedEnquiry->branch_id,
                'application_no' => $this->generateApplicationNo(),
                'name' => $lockedEnquiry->name,
                'mobile' => $lockedEnquiry->mobile,
                'email' => $lockedEnquiry->email,
                'address' => null,
                'status' => 'new',
                'remarks' => trim('Converted from enquiry '.$lockedEnquiry->enquiry_no.'. '.$lockedEnquiry->follow_up_notes),
            ]);

            $lockedEnquiry->update(['status' => 'converted', 'converted_admission_id' => $admission->id, 'follow_up_date' => null]);

            return $admission;
        }, 3);

        $enquiry->refresh();
        $audit->log('enquiry.converted_to_admission', $enquiry, $old, [
            'status' => $enquiry->status,
            'converted_admission_id' => $admission->id,
            'application_no' => $admission->application_no,
        ]);

        return redirect()->route('admin.admissions.show', $admission)
            ->with('success', 'Enquiry converted to admission application.');
    }

    private function generateApplicationNo(): string
    {
        do {
            $number = 'CNL-ADM-'.now()->format('Y').'-'.strtoupper(Str::random(8));
        } while (Admission::query()->where('application_no', $number)->exists());

        return $number;
    }
}
