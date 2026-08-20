<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreAdmissionRequest;
use App\Models\Admission;
use App\Models\Branch;
use App\Models\FeePlan;
use App\Models\StudySlot;
use App\Services\ApplicationNumberService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class AdmissionController extends Controller
{
    public function create(): View
    {
        return view('public.admission', [
            'branches' => Branch::query()->where('status', true)->orderBy('name')->get(),
            'studySlots' => StudySlot::query()->with('branch')->where('status', true)->orderBy('name')->get(),
            'feePlans' => FeePlan::query()->with('branch')->where('status', true)->orderBy('name')->get(),
        ]);
    }

    public function store(
        StoreAdmissionRequest $request,
        ApplicationNumberService $applicationNumbers
    ): RedirectResponse {
        $data = $request->validated();
        $mobile = trim((string) $data['mobile']);

        $existing = Admission::query()
            ->where('branch_id', $data['branch_id'])
            ->where('mobile', $mobile)
            ->whereIn('status', ['new', 'under_review', 'approved'])
            ->where('created_at', '>=', now()->subDays(7))
            ->latest('id')
            ->first();

        if ($existing) {
            return back()
                ->withInput()
                ->withErrors([
                    'mobile' => "An active application already exists for this mobile number. Reference: {$existing->application_no}.",
                ]);
        }

        $data['mobile'] = $mobile;
        $data['application_no'] = $applicationNumbers->generate();
        $data['status'] = 'new';

        $admission = Admission::create($data);

        return redirect()
            ->route('admission.create')
            ->with('success', "Application submitted successfully. Your application number is {$admission->application_no}.");
    }
}
