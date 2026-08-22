<?php

namespace Database\Factories;

use App\Models\Payment;
use App\Models\Student;
use App\Models\StudentMembership;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<Payment> */
class PaymentFactory extends Factory
{
    protected $model = Payment::class;

    public function definition(): array
    {
        return [
            'student_id' => Student::factory(),
            'student_membership_id' => StudentMembership::factory(),
            'receipt_no' => 'TEST-'.Str::upper(Str::random(16)),
            'amount' => 1000,
            'discount' => 0,
            'late_fee' => 0,
            'payment_date' => today(),
            'payment_mode' => 'cash',
            'transaction_ref' => null,
            'payment_status' => 'paid',
            'received_by' => User::factory(),
            'remarks' => null,
        ];
    }
}
