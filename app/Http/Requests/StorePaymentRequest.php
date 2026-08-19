<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StorePaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'student_membership_id' => ['required', 'exists:student_memberships,id'],
            'amount' => ['required', 'numeric', 'min:1'],
            'payment_mode' => ['required', 'in:cash,upi,card,bank_transfer,other'],
            'transaction_ref' => ['nullable', 'string', 'max:255'],
            'remarks' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
