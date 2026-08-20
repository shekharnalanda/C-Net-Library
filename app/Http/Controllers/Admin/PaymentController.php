<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StorePaymentRequest;
use App\Models\Payment;
use App\Models\PaymentAdjustment;
use App\Models\Student;
use App\Models\StudentMembership;
use App\Services\AuditService;
use App\Services\ReceiptService;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class PaymentController extends Controller
{
    public function store(
        StorePaymentRequest $request,
        Student $student,
        ReceiptService $receiptService,
        AuditService $auditService
    ) {
        try {
            $payment = DB::transaction(function () use ($request, $student, $receiptService) {
                $membership = StudentMembership::query()
                    ->whereKey($request->integer('student_membership_id'))
                    ->where('student_id', $student->id)
                    ->lockForUpdate()
                    ->firstOrFail();

                $grossPaid = (float) $membership->payments()
                    ->whereIn('payment_status', ['paid', 'partial'])
                    ->sum('amount');
                $adjusted = (float) PaymentAdjustment::query()
                    ->whereHas('payment', fn ($query) => $query->where('student_membership_id', $membership->id))
                    ->sum('amount');
                $alreadyPaid = max(0, $grossPaid - $adjusted);

                $membershipFee = (float) $membership->final_fee;
                $due = max(0, $membershipFee - $alreadyPaid);
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

                $balanceAfterPayment = max(0, $membershipFee - ($alreadyPaid + $amount));

                return Payment::create([
                    'student_id' => $student->id,
                    'student_membership_id' => $membership->id,
                    'receipt_no' => $receiptService->generate(branchId: $student->branch_id),
                    'amount' => $amount,
                    'receipt_previous_paid' => $alreadyPaid,
                    'receipt_balance_due' => $balanceAfterPayment,
                    'receipt_membership_fee' => $membershipFee,
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
        } catch (QueryException $exception) {
            if ($this->isDuplicateTransactionReference($exception)) {
                throw ValidationException::withMessages([
                    'transaction_ref' => 'This transaction reference has already been recorded.',
                ]);
            }

            throw $exception;
        }

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

    public function adjust(Request $request, Payment $payment, AuditService $auditService)
    {
        $data = $request->validate([
            'type' => ['required', Rule::in(['refund', 'reversal', 'correction'])],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'reason' => ['required', 'string', 'max:1000'],
        ]);

        $adjustment = DB::transaction(function () use ($payment, $data) {
            $lockedPayment = Payment::query()->whereKey($payment->id)->lockForUpdate()->firstOrFail();
            abort_unless(in_array($lockedPayment->payment_status, ['paid', 'partial'], true), 422);

            $alreadyAdjusted = (float) $lockedPayment->adjustments()->sum('amount');
            $remainingAdjustable = max(0, (float) $lockedPayment->amount - $alreadyAdjusted);
            $amount = (float) $data['amount'];

            if ($amount > $remainingAdjustable) {
                throw ValidationException::withMessages([
                    'amount' => 'Adjustment amount cannot exceed the unadjusted payment amount.',
                ]);
            }

            return $lockedPayment->adjustments()->create([
                'type' => $data['type'],
                'amount' => $amount,
                'reason' => $data['reason'],
                'created_by' => auth()->id(),
            ]);
        });

        $auditService->log(
            action: 'payment.adjustment.created',
            auditable: $adjustment,
            newValues: $adjustment->only(['payment_id', 'type', 'amount', 'reason']),
            request: $request,
        );

        return back()->with('success', 'Payment adjustment recorded. Original payment remains unchanged.');
    }

    public function receipt(Payment $payment)
    {
        $payment->load([
            'student.branch',
            'membership.studySlot',
            'membership.feePlan',
            'receiver',
            'adjustments.creator',
        ]);

        if ($payment->receipt_previous_paid !== null
            && $payment->receipt_balance_due !== null
            && $payment->receipt_membership_fee !== null) {
            $previousPaid = (float) $payment->receipt_previous_paid;
            $balanceDue = (float) $payment->receipt_balance_due;
            $membershipFeeAtIssue = (float) $payment->receipt_membership_fee;
        } else {
            // Legacy receipts created before immutable receipt snapshots were introduced.
            $previousGross = (float) Payment::query()
                ->where('student_membership_id', $payment->student_membership_id)
                ->where('id', '<', $payment->id)
                ->whereIn('payment_status', ['paid', 'partial'])
                ->sum('amount');
            $previousAdjustments = (float) PaymentAdjustment::query()
                ->whereHas('payment', fn ($query) => $query
                    ->where('student_membership_id', $payment->student_membership_id)
                    ->where('id', '<', $payment->id))
                ->sum('amount');
            $previousPaid = max(0, $previousGross - $previousAdjustments);
            $membershipFeeAtIssue = (float) $payment->membership->final_fee;
            $balanceDue = max(0, $membershipFeeAtIssue - ($previousPaid + (float) $payment->amount));
        }

        $currentAdjusted = (float) $payment->adjustments->sum('amount');
        $currentNet = max(0, (float) $payment->amount - $currentAdjusted);

        return response()
            ->view('admin.payments.receipt', compact(
                'payment',
                'previousPaid',
                'balanceDue',
                'currentAdjusted',
                'currentNet',
                'membershipFeeAtIssue'
            ))
            ->header('Cache-Control', 'private, no-store, no-cache, must-revalidate')
            ->header('Pragma', 'no-cache')
            ->header('X-Robots-Tag', 'noindex, nofollow, noarchive');
    }

    private function isDuplicateTransactionReference(QueryException $exception): bool
    {
        $message = strtolower($exception->getMessage());

        return str_contains($message, 'payments_transaction_ref_unique')
            || str_contains($message, 'payments.transaction_ref')
            || str_contains($message, 'duplicate entry') && str_contains($message, 'transaction_ref');
    }
}
