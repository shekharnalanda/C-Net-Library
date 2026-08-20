<?php

namespace App\Http\Requests;

use App\Models\FeePlan;
use App\Models\StudySlot;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\ValidationException;

class StoreAdmissionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'branch_id' => ['required', 'exists:branches,id'],
            'name' => ['required', 'string', 'max:255'],
            'father_name' => ['nullable', 'string', 'max:255'],
            'dob' => ['nullable', 'date', 'before:today'],
            'gender' => ['nullable', 'string', 'max:30'],
            'mobile' => ['required', 'string', 'max:20'],
            'email' => ['nullable', 'email', 'max:255'],
            'address' => ['nullable', 'string', 'max:2000'],
            'study_slot_id' => ['nullable', 'exists:study_slots,id'],
            'fee_plan_id' => ['nullable', 'exists:fee_plans,id'],
        ];
    }

    protected function passedValidation(): void
    {
        $branchId = (int) $this->input('branch_id');

        if ($this->filled('study_slot_id')) {
            $validSlot = StudySlot::query()
                ->whereKey($this->integer('study_slot_id'))
                ->where('branch_id', $branchId)
                ->where('status', true)
                ->exists();

            if (! $validSlot) {
                throw ValidationException::withMessages([
                    'study_slot_id' => 'Selected study slot is not available for this branch.',
                ]);
            }
        }

        if ($this->filled('fee_plan_id')) {
            $validPlan = FeePlan::query()
                ->whereKey($this->integer('fee_plan_id'))
                ->where('branch_id', $branchId)
                ->where('status', true)
                ->exists();

            if (! $validPlan) {
                throw ValidationException::withMessages([
                    'fee_plan_id' => 'Selected fee plan is not available for this branch.',
                ]);
            }
        }
    }
}
