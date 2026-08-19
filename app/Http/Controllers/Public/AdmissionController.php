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
            'studySlots' => StudySlot::query()->where('status', true)->orderBy('name')->get(),
            'feePlans' => FeePlan::query()->where('status', true)->orderBy('name')->get(),
        ]);
    }

    public function store(
        StoreAdmissionRequest $request,
        ApplicationNumberService $applicationNumbers
    ): RedirectResponse {
        $data = $request->validated();
        $data['application_no'] = $applicationNumbers->generate();
        $data['status'] = 'new';

        $admission = Admission::create($data);

        return redirect()
            ->route('admission.create')
            ->with('success', "Application submitted successfully. Your application number is {$admission->application_no}.");
    }
}
