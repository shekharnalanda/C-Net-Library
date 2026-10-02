<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Services\LibraryAdmissionAvailability;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdmissionAvailabilityController extends Controller
{
    public function __invoke(Request $request, LibraryAdmissionAvailability $service): JsonResponse
    {
        $d = $request->validate(['branch_id' => ['required', 'integer', 'exists:branches,id'], 'study_slot_id' => ['required', 'integer'], 'fee_plan_id' => ['required', 'integer'], 'start_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:today', 'before_or_equal:'.today()->addYear()->toDateString()]]);

        return response()->json($service->options($d['branch_id'], $d['study_slot_id'], $d['fee_plan_id'], $d['start_date']))->header('Cache-Control', 'no-store');
    }
}
