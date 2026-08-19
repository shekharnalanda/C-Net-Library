<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Admission;
use App\Models\Branch;
use App\Models\Enquiry;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;

class EnquiryController extends Controller
{
    public function index(Request $request): View
    {
        $enquiries = Enquiry::query()
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
            'branches' => Branch::query()->where('status', true)->orderBy('name')->get(),
            'staff' => User::query()->whereIn('role', ['super_admin', 'branch_admin', 'counselor', 'admin'])->orderBy('name')->get(),
        ]);
    }

    public function update(Request $request, Enquiry $enquiry): RedirectResponse
    {
        $data = $request->validate([
            'status' => ['required', 'in:new,contacted,follow_up,qualified,converted,lost'],
            'assigned_to' => ['nullable', 'exists:users,id'],
            'follow_up_date' => ['nullable', 'date'],
            'follow_up_notes' => ['nullable', 'string', 'max:3000'],
        ]);

        $enquiry->update($data);

        return back()->with('success', 'Enquiry updated.');
    }

    public function convert(Enquiry $enquiry): RedirectResponse
    {
        if ($enquiry->converted_admission_id) {
            return redirect()->route('admin.admissions.show', $enquiry->converted_admission_id);
        }

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
