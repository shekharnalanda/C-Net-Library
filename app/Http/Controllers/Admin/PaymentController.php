<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StorePaymentRequest;
use App\Models\Payment;
use App\Models\Student;
use App\Models\StudentMembership;
use App\Services\AuditService;
use App\Services\ReceiptService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PaymentController extends Controller
{
    public function store(
        StorePaymentRequest $request,
        Student $student,
        ReceiptService $receiptService,
        AuditService $auditService
    ) {
        $payment = DB::transaction(function () use ($request, $student, $receiptService) {
            $membership = StudentMembership::query()
                ->whereKey($request->integer('student_membership_id'))
                ->where('student_id', $student->id)
                ->lockForUpdate()
                ->firstOrFail();

            $alreadyPaid = (float) $membership->payments()
                ->whereIn('payment_status', ['paid', 'partial'])
                ->sum('amount');

            $due = max(0, (float) $membership->final_fee - $alreadyPaid);
            $amount = (float) $request->input('amount');

            if ($due <= 0) {
                throw ValidationException::withMessages([
                    'amount' => 'This membership has no outstanding balance.',
                ]);
            }

            if ($amount > $due) {
                throw ValidationException::withMessages([
                    'amount' => 'Payment current due amount se zyada nahi ho sakta.',
                ]);
            }

            $transactionRef = trim((string) $request->input('transaction_ref'));
            if ($transactionRef !== '') {
                $duplicateReference = Payment::query()
                    ->where('transaction_ref', $transactionRef)
                    ->whereIn('payment_status', ['paid', 'partial'])
                    ->exists();

                if ($duplicateReference) {
                    throw ValidationException::withMessages([
                        'transaction_ref' => 'This transaction reference has already been recorded.',
                    ]);
                }
            }

            return Payment::create([
                'student_id' => $student->id,
                'student_membership_id' => $membership->id,
                'receipt_no' => $receiptService->generate(branchId: $student->branch_id),
                'amount' => $amount,
                'discount' => 0,
                'late_fee' => 0,
                'payment_date' => today(),
                'payment_mode' => $request->input('payment_mode'),
                'transaction_ref' => $transactionRef !== '' ? $transactionRef : null,
                'payment_status' => $amount >= $due ? 'paid' : 'partial',
                'received_by' => auth()->id(),
                'remarks' => $request->input('remarks'),
            ]);
        });

        $auditService->log(
            action: 'payment.received',
            auditable: $payment,
            newValues: $payment->only([
                'student_id',
                'student_membership_id',
                'receipt_no',
                'amount',
                'payment_mode',
                'payment_status',
                'transaction_ref',
            ]),
            request: $request,
        );

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

        return response()
            ->view('admin.payments.receipt', compact('payment', 'previousPaid', 'balanceDue'))
            ->header('Cache-Control', 'private, no-store, no-cache, must-revalidate')
            ->header('Pragma', 'no-cache')
            ->header('X-Robots-Tag', 'noindex, nofollow, noarchive');
    }
}
