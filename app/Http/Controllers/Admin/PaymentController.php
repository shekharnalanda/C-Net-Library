<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StorePaymentRequest;
use App\Models\Payment;
use App\Models\Student;
use App\Models\StudentMembership;
use App\Services\ReceiptService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PaymentController extends Controller
{
    public function store(StorePaymentRequest $request, Student $student, ReceiptService $receiptService)
    {
        $membership = StudentMembership::query()
            ->whereKey($request->integer('student_membership_id'))
            ->where('student_id', $student->id)
            ->firstOrFail();

        $alreadyPaid = (float) $membership->payments()
            ->whereIn('payment_status', ['paid', 'partial'])
            ->sum('amount');

        $due = max(0, (float) $membership->final_fee - $alreadyPaid);
        $amount = (float) $request->input('amount');

        if ($amount > $due) {
            throw ValidationException::withMessages([
                'amount' => 'Payment current due amount se zyada nahi ho sakta.',
            ]);
        }

        $payment = DB::transaction(function () use ($request, $student, $membership, $receiptService, $amount, $due) {
            return Payment::create([
                'student_id' => $student->id,
                'student_membership_id' => $membership->id,
                'receipt_no' => $receiptService->generate(),
                'amount' => $amount,
                'discount' => 0,
                'late_fee' => 0,
                'payment_date' => today(),
                'payment_mode' => $request->input('payment_mode'),
                'transaction_ref' => $request->input('transaction_ref'),
                'payment_status' => $amount >= $due ? 'paid' : 'partial',
                'received_by' => auth()->id(),
                'remarks' => $request->input('remarks'),
            ]);
        });

        return redirect()->route('admin.payments.receipt', $payment)
            ->with('success', 'Payment received successfully.');
    }

    public function receipt(Payment $payment)
    {
        $payment->load([
            'student.branch',
            'membership.studySlot',
            'membership.feePlan',
            'receiver',
        ]);

        $previousPaid = (float) Payment::query()
            ->where('student_membership_id', $payment->student_membership_id)
            ->where('id', '<', $payment->id)
            ->whereIn('payment_status', ['paid', 'partial'])
            ->sum('amount');

        $balanceDue = max(
            0,
            (float) $payment->membership->final_fee - ($previousPaid + (float) $payment->amount)
        );

        return view('admin.payments.receipt', compact('payment', 'previousPaid', 'balanceDue'));
    }
}
