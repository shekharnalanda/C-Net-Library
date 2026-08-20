<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAdmissionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $branchId = $this->input('branch_id');

        return [
            'website' => ['nullable', 'string', 'max:0'],
            'branch_id' => [
                'required',
                Rule::exists('branches', 'id')->where(fn ($query) => $query->where('status', true)),
            ],
            'name' => ['required', 'string', 'max:255'],
            'father_name' => ['nullable', 'string', 'max:255'],
            'dob' => ['nullable', 'date', 'before_or_equal:today'],
            'gender' => ['nullable', Rule::in(['male', 'female', 'other'])],
            'mobile' => ['required', 'string', 'max:20'],
            'email' => ['nullable', 'email', 'max:255'],
            'address' => ['nullable', 'string', 'max:2000'],
            'study_slot_id' => [
                'nullable',
                Rule::exists('study_slots', 'id')->where(fn ($query) => $query
                    ->where('branch_id', $branchId)
                    ->where('status', true)),
            ],
            'fee_plan_id' => [
                'nullable',
                Rule::exists('fee_plans', 'id')->where(fn ($query) => $query
                    ->where('branch_id', $branchId)
                    ->where('status', true)),
            ],
        ];
    }
}
