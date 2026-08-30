<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreAdmissionRequest;
use App\Models\Admission;
use App\Models\Branch;
use App\Models\FeePlan;
use App\Models\StudySlot;
use App\Services\ApplicationNumberService;
use App\Services\CentralSyncService;
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
        ApplicationNumberService $applicationNumbers,
        CentralSyncService $centralSync
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
        $branch = Branch::find($admission->branch_id);
        $studySlot = $admission->study_slot_id ? StudySlot::find($admission->study_slot_id) : null;
        $feePlan = $admission->fee_plan_id ? FeePlan::find($admission->fee_plan_id) : null;

        $centralSync->admission([
            'business_code' => config('services.mci_central.business_code'),
            'source_reference_id' => 'library-admission-'.$admission->application_no,
            'source_site' => config('app.url', 'https://cnetlibrary.mciedu.com'),
            'application_reference' => $admission->application_no,
            'applicant_name' => $admission->name,
            'phone' => $admission->mobile,
            'email' => $admission->email,
            'course_program' => $feePlan?->name ?: ($studySlot?->name ?: 'Library Membership'),
            'status' => $admission->status,
            'payment_status' => 'unpaid',
            'submitted_at' => ($admission->created_at ?: now())->toIso8601String(),
            'metadata' => [
                'branch_id' => $admission->branch_id,
                'branch_name' => $branch?->name,
                'study_slot_id' => $admission->study_slot_id,
                'study_slot' => $studySlot?->name,
                'fee_plan_id' => $admission->fee_plan_id,
                'fee_plan' => $feePlan?->name,
                'father_name' => $admission->father_name,
                'address' => $admission->address,
            ],
        ]);

        return redirect()
            ->route('admission.create')
            ->with('success', "Application submitted successfully. Your application number is {$admission->application_no}.");
    }
}
