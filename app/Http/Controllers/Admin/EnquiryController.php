<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Admission;
use App\Models\Branch;
use App\Models\Enquiry;
use App\Models\User;
use App\Services\AuditService;
use App\Support\AdminBranchScope;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;

class EnquiryController extends Controller
{
    public function index(Request $request): View
    {
        $branchId = AdminBranchScope::id($request);
        $query = AdminBranchScope::apply(Enquiry::query(), $request);

        $enquiries = $query
            ->with(['branch', 'assignee', 'convertedAdmission'])
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')))
            ->when($request->filled('q'), function ($query) use ($request) {
                $term = '%'.$request->string('q').'%';
                $query->where(function ($sub) use ($term) {
                    $sub->where('name', 'like', $term)
                        ->orWhere('mobile', 'like', $term)
                        ->orWhere('enquiry_no', 'like', $term);
                });
            })
            ->latest()
            ->paginate(25)
            ->withQueryString();

        return view('admin.enquiries.index', [
            'enquiries' => $enquiries,
            'branches' => $branchId === null
                ? Branch::query()->where('status', true)->orderBy('name')->get()
                : Branch::query()->whereKey($branchId)->get(),
            'staff' => User::query()
                ->whereIn('role', ['super_admin', 'branch_admin', 'counselor', 'admin'])
                ->when($branchId !== null, fn ($query) => $query->where('branch_id', $branchId))
                ->orderBy('name')
                ->get(),
        ]);
    }

    public function update(Request $request, Enquiry $enquiry, AuditService $audit): RedirectResponse
    {
        $data = $request->validate([
            'status' => ['required', 'in:new,contacted,follow_up,qualified,converted,lost'],
            'assigned_to' => ['nullable', 'exists:users,id'],
            'follow_up_date' => ['nullable', 'date'],
            'follow_up_notes' => ['nullable', 'string', 'max:3000'],
        ]);

        if (! empty($data['assigned_to']) && ! $request->user()->isGlobalAdmin()) {
            $validAssignee = User::query()
                ->whereKey($data['assigned_to'])
                ->where('branch_id', $request->user()->branch_id)
                ->exists();
            abort_unless($validAssignee, 403);
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
        if ($enquiry->converted_admission_id) {
            return redirect()->route('admin.admissions.show', $enquiry->converted_admission_id);
        }

        $old = $enquiry->only(['status', 'converted_admission_id']);

        $admission = DB::transaction(function () use ($enquiry) {
            $applicationNo = $this->generateApplicationNo();

            $admission = Admission::create([
                'branch_id' => $enquiry->branch_id,
                'application_no' => $applicationNo,
                'name' => $enquiry->name,
                'mobile' => $enquiry->mobile,
                'email' => $enquiry->email,
                'address' => null,
                'status' => 'pending',
                'remarks' => trim('Converted from enquiry '.$enquiry->enquiry_no.'. '.$enquiry->follow_up_notes),
            ]);

            $enquiry->update([
                'status' => 'converted',
                'converted_admission_id' => $admission->id,
            ]);

            return $admission;
        });

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
            $number = 'CNL-ADM-'.now()->format('Y').'-'.strtoupper(Str::random(6));
        } while (Admission::query()->where('application_no', $number)->exists());

        return $number;
    }
}
