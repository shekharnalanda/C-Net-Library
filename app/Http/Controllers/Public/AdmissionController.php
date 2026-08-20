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
        unset($data['website']);

        $data['name'] = trim((string) $data['name']);
        $data['father_name'] = isset($data['father_name']) && $data['father_name'] !== ''
            ? trim((string) $data['father_name'])
            : null;
        $data['mobile'] = preg_replace('/\s+/', '', trim((string) $data['mobile']));
        $data['email'] = isset($data['email']) && $data['email'] !== ''
            ? strtolower(trim((string) $data['email']))
            : null;
        $data['address'] = isset($data['address']) && $data['address'] !== '' ? trim((string) $data['address']) : null;

        $existing = Admission::query()
            ->where('branch_id', $data['branch_id'])
            ->where('mobile', $data['mobile'])
            ->whereIn('status', ['new', 'under_review', 'approved'])
            ->where('created_at', '>=', now()->subDays(7))
            ->latest('id')
            ->first();

        if ($existing) {
            return redirect()
                ->route('admission.create')
                ->with('success', 'Your application has been received. Our team will follow up if any action is needed.');
        }

        $data['application_no'] = $applicationNumbers->generate();
        $data['status'] = 'new';

        $admission = Admission::create($data);

        return redirect()
            ->route('admission.create')
            ->with('success', "Application submitted successfully. Your application number is {$admission->application_no}.");
    }
}
