<?php
namespace App\Services;

use App\Models\Admission;
use App\Models\Branch;
use App\Models\FeePlan;
use App\Models\MciPayOrder;
use App\Models\Payment;
use App\Models\PaymentAdjustment;
use App\Models\Student;
use App\Models\StudentMembership;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class MciFeeAdapter
{
    public function admissionAccess(Request $request): void
    {
        $data = $request->validate(['application_no'=>['required','string','max:100'],'phone'=>['required','string','max:20']]);
        $record = Admission::where('application_no', trim($data['application_no']))->where('status','!=','rejected')->first();
        $provided = preg_replace('/\D/', '', $data['phone']);
        $stored = preg_replace('/\D/', '', (string)($record?->mobile ?? ''));
        if (!$record || strlen($provided)<10 || !hash_equals($stored,$provided)) {
            throw ValidationException::withMessages(['application_no'=>'आवेदन नंबर और दर्ज मोबाइल नंबर का मिलान नहीं हुआ।']);
        }
        $request->session()->regenerate();
        $request->session()->put('mci_pay_admission_id',$record->id);
    }

    private function actor(Request $request): array
    {
        $studentUser = config('auth.guards.library_student')
            ? $request->user('library_student') : $request->user('web');
        if ($studentUser?->role === 'student') {
            abort_unless($studentUser->status, 403);
            $student = Student::where('user_id', $studentUser->id)->where('status', 'active')->firstOrFail();
            abort_unless(Branch::whereKey($student->branch_id)->where('status', true)->exists(), 403);
            return ['type' => 'student', 'id' => (string) $student->id, 'record' => $student];
        }
        if ($request->session()->has('mci_pay_admission_id')) {
            $admission = Admission::findOrFail($request->session()->get('mci_pay_admission_id'));
            abort_if($admission->status === 'rejected', 403);
            return ['type' => 'admission', 'id' => (string) $admission->id, 'record' => $admission];
        }
        throw new HttpResponseException(redirect()->route('mci-pay.access'));
    }

    private function due(StudentMembership $membership): int
    {
        $paid = (float) $membership->payments()->whereIn('payment_status', ['paid', 'partial'])->sum('amount');
        $adjusted = (float) PaymentAdjustment::whereHas('payment', fn ($q) => $q->where('student_membership_id', $membership->id))->sum('amount');
        return max(0, (int) round(((float) $membership->final_fee - max(0, $paid - $adjusted)) * 100));
    }

    private function choices(array $actor): array
    {
        $record = $actor['record'];
        if ($actor['type'] === 'admission' && ! $record->mci_student_id) {
            $plan = FeePlan::whereKey($record->fee_plan_id)->where('branch_id', $record->branch_id)->where('status', true)->first();
            if (! $plan || (float) $plan->monthly_fee <= 0) { return []; }
            return [['key' => 'admission', 'label' => 'एडमिशन - प्रारंभिक सदस्यता फीस', 'amount_paise' => (int) round((float) $plan->monthly_fee * 100), 'month' => now()->format('Y-m'), 'plan_id' => $plan->id]];
        }
        $studentId = $actor['type'] === 'student' ? $record->id : $record->mci_student_id;
        return StudentMembership::where('student_id', $studentId)->whereIn('status', ['active', 'expired'])->orderBy('start_date')->get()
            ->map(fn ($m) => ['key' => 'membership:'.$m->id, 'label' => 'सदस्यता फीस '.$m->start_date->format('d M Y').' - '.$m->expiry_date->format('d M Y'), 'amount_paise' => $this->due($m), 'month' => $m->start_date->format('Y-m'), 'membership_id' => $m->id])
            ->filter(fn ($c) => $c['amount_paise'] >= 100)->values()->all();
    }

    public function page(Request $request): array
    {
        $actor = $this->actor($request); $r = $actor['record'];
        return ['payerName' => $r->name, 'studentReference' => $r->student_code ?? $r->application_no,
            'choices' => $this->choices($actor), 'orders' => MciPayOrder::where('principal_type', $actor['type'])->where('principal_id', $actor['id'])->latest()->get()];
    }

    public function quote(Request $request): array
    {
        $actor = $this->actor($request); $r = $actor['record'];
        $choice = collect($this->choices($actor))->firstWhere('key', (string) $request->input('choice'));
        if (! $choice) { throw ValidationException::withMessages(['choice' => 'यह फीस अभी देय नहीं है। पेज दोबारा खोलें।']); }
        return ['branch_id' => $r->branch_id, 'principal_type' => $actor['type'], 'principal_id' => $actor['id'],
            'active_key' => $actor['type'].':'.$actor['id'].':'.$choice['key'], 'student_reference' => $r->student_code ?? $r->application_no,
            'payer_name' => $r->name, 'payer_phone' => $r->mobile, 'purpose' => $choice['label'], 'billing_month' => $choice['month'],
            'amount_paise' => $choice['amount_paise'], 'metadata' => $choice];
    }

    public function adminQuery(Request $request)
    {
        $user = $request->user('web');
        abort_unless($user && $user->status && $user->role !== 'student' && $user->canAccess('payments.manage'), 403);
        if (! $user->isGlobalAdmin()) { abort_unless($user->branch_id && Branch::whereKey($user->branch_id)->where('status', true)->exists(), 403); }
        return MciPayOrder::query()->when(! $user->isGlobalAdmin(), fn ($q) => $q->where('branch_id', $user->branch_id));
    }

    public function authorize(Request $request, MciPayOrder $order): void
    {
        if ($request->user('web') && $request->user('web')->role !== 'student') {
            abort_unless($this->adminQuery($request)->whereKey($order->id)->exists(), 403); return;
        }
        $actor = $this->actor($request);
        abort_unless($order->principal_type === $actor['type'] && $order->principal_id === $actor['id'], 403);
    }

    public function apply(MciPayOrder $order): ?string
    {
        $membershipId = $order->metadata['membership_id'] ?? null;
        if ($order->principal_type === 'admission' && ! $membershipId) {
            $admission = Admission::findOrFail($order->principal_id);
            if (! $admission->mci_student_id) { return null; }
            $membershipId = StudentMembership::where('student_id', $admission->mci_student_id)->oldest('id')->value('id');
        }
        $membership = StudentMembership::lockForUpdate()->findOrFail($membershipId);
        $student = Student::findOrFail($membership->student_id);
        if ($order->principal_type === 'student' && (string) $student->id !== $order->principal_id) { abort(409); }
        $existing = Payment::where('transaction_ref', $order->reference)->first();
        if ($existing) {
            if ((int) $existing->student_membership_id === (int) $membership->id && (int) round((float) $existing->amount * 100) === $order->amount_paise) { return $existing->receipt_no; }
            throw ValidationException::withMessages(['payment' => 'यह UTR दूसरे फीस रिकॉर्ड में मौजूद है। बैंक और पुराने रिकॉर्ड का मिलान करें।']);
        }
        $due = $this->due($membership);
        if ($order->amount_paise > $due) { throw ValidationException::withMessages(['payment' => 'बैंक में भुगतान मिला है, लेकिन वर्तमान बकाया राशि कम है। कार्यालय रकम का समायोजन जाँचे।']); }
        $amount = $order->amount_paise / 100;
        $payment = Payment::create([
            'student_id' => $student->id, 'student_membership_id' => $membership->id,
            'receipt_no' => app(ReceiptService::class)->generate(branchId: $student->branch_id),
            'amount' => $amount, 'receipt_previous_paid' => (float) $membership->final_fee - $due / 100,
            'receipt_balance_due' => ($due - $order->amount_paise) / 100, 'receipt_membership_fee' => $membership->final_fee,
            'discount' => 0, 'late_fee' => 0, 'payment_date' => $order->payment_date, 'payment_mode' => 'upi',
            'transaction_ref' => $order->reference, 'payment_status' => $order->amount_paise === $due ? 'paid' : 'partial',
            'received_by' => null, 'remarks' => 'Bank verified via MCI Pay. '.$order->central_receipt.' / '.$order->id,
        ]);
        app(AuditService::class)->log('payment.mci_upi_verified', $payment, [], $payment->only(['receipt_no', 'amount', 'transaction_ref']), request());
        return $payment->receipt_no;
    }
}
