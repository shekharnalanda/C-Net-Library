<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ApproveAdmissionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'fee_plan_id' => ['required', 'exists:fee_plans,id'],
            'study_slot_id' => ['required', 'exists:study_slots,id'],
            'seat_id' => ['required', 'exists:seats,id'],
            'start_date' => ['nullable', 'date'],
            'discount' => ['nullable', 'numeric', 'min:0'],
            'remarks' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
