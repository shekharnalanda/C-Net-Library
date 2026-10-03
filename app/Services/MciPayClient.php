<?php
namespace App\Services;

use App\Models\MciPayOrder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class MciPayClient
{
    public static function sign(string $secret, string $path, string $timestamp, string $body): string
    {
        return hash_hmac('sha256', "POST\n{$path}\n{$timestamp}\n{$body}", $secret);
    }

    public function create(array $quote): MciPayOrder
    {
        abort_unless(config('mci_pay.enabled') && strlen((string)config('mci_pay.secret'))>=32,503,'Online UPI payment is being configured.');
        $order = DB::transaction(function () use ($quote) {
            return MciPayOrder::firstOrCreate(['active_key'=>$quote['active_key']], ['id'=>(string)Str::uuid()]+$quote);
        },3);
        if (!$order->central_id) {
            $snapshot=$this->request('checkout',[
                'order_id'=>$order->id,'amount_paise'=>$order->amount_paise,'currency'=>'INR',
                'student_reference'=>$order->student_reference,'payer_name'=>$order->payer_name,
                'payer_phone'=>$order->payer_phone,'purpose'=>$order->purpose,'billing_month'=>$order->billing_month,
            ]);
            $this->applySnapshot($order,$snapshot);
        }
        return $order->fresh();
    }

    public function refresh(MciPayOrder $order): void
    {
        if (!$order->central_id) { return; }
        $this->applySnapshot($order,$this->request('status',['order_id'=>$order->id]));
    }

    private function request(string $action,array $payload): array
    {
        $secret=(string)config('mci_pay.secret');
        abort_unless(strlen($secret)>=32,503,'Payment connection is not configured.');
        $path='/api/upi/'.$action;$timestamp=(string)time();
        $body=json_encode($payload,JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);
        try {
            $response=Http::withHeaders([
                'X-MCI-Integration'=>config('mci_pay.integration'),'X-MCI-Timestamp'=>$timestamp,
                'X-MCI-Signature'=>self::sign($secret,$path,$timestamp,$body),'Accept'=>'application/json',
            ])->withOptions(['allow_redirects'=>false])->connectTimeout(4)->timeout(15)
                ->withBody($body,'application/json')->post(config('mci_pay.base_url').$path);
            if (!$response->successful() || !is_array($response->json())) {
                throw new \RuntimeException('Payment service unavailable');
            }
            return $response->json();
        } catch (\Throwable $error) {
            throw ValidationException::withMessages(['payment'=>'पेमेंट सेवा से अभी संपर्क नहीं हुआ। आपका रिकॉर्ड सुरक्षित है; थोड़ी देर में फिर कोशिश करें। पैसा कट चुका हो तो दोबारा भुगतान न करें।']);
        }
    }

    public function authenticateCallback(Request $request): void
    {
        $secret=(string)config('mci_pay.secret');$timestamp=(string)$request->header('X-MCI-Timestamp');
        abort_unless(strlen($secret)>=32 && ctype_digit($timestamp) && abs(time()-(int)$timestamp)<=300,401);
        abort_unless($request->header('X-MCI-Integration')===config('mci_pay.integration'),401);
        abort_unless(strlen($request->getContent())<=16384,413);
        abort_unless(hash_equals(self::sign($secret,'/mci-pay/callback',$timestamp,$request->getContent()),strtolower((string)$request->header('X-MCI-Signature'))),401);
    }

    public function applySnapshot(MciPayOrder $order,array $snapshot): void
    {
        $data=Validator::make($snapshot,[
            'order_id'=>['required','uuid'],'central_id'=>['required','uuid'],
            'amount_paise'=>['required','integer','min:100'],'currency'=>['required','in:INR'],
            'revision'=>['required','integer','min:0'],'status'=>['required','in:created,pending,verified,rejected'],
            'reference'=>['nullable','string','max:22'],'payment_date'=>['nullable','date_format:Y-m-d'],
            'review_note'=>['nullable','string','max:1000'],'receipt_no'=>['nullable','string','max:100'],
            'checkout_url'=>['required','url','max:250'],
        ])->validate();
        abort_unless(str_starts_with($data['checkout_url'],config('mci_pay.base_url').'/pay/upi/')
            && preg_match('#/pay/upi/[A-Za-z0-9]{64}$#',$data['checkout_url']),422);
        DB::transaction(function () use ($order,$data): void {
            $locked=MciPayOrder::lockForUpdate()->findOrFail($order->id);
            abort_unless($data['order_id']===$locked->id && (int)$data['amount_paise']===$locked->amount_paise,409);
            abort_unless(!$locked->central_id || $locked->central_id===$data['central_id'],409);
            if ((int)$data['revision']<$locked->revision || $locked->status==='applied') { return; }
            if (in_array($locked->status,['verified','needs_review'],true) && $data['status']!=='verified') { return; }
            $locked->fill([
                'central_id'=>$data['central_id'],'checkout_url'=>$data['checkout_url'],'revision'=>$data['revision'],
                'status'=>$data['status'],'reference'=>$data['reference']??null,'payment_date'=>$data['payment_date']??null,
                'review_note'=>$data['review_note']??null,'central_receipt'=>$data['receipt_no']??null,'sync_error'=>null,
            ])->save();
            if ($data['status']==='verified') {
                abort_unless(!empty($data['reference']) && !empty($data['payment_date']) && !empty($data['receipt_no']),422);
                $this->applyVerified($locked);
            }
        },3);
    }

    public function applyVerified(MciPayOrder $order): void
    {
        if (!in_array($order->status,['verified','needs_review'],true)) { return; }
        try {
            $receipt=DB::transaction(fn()=>app(MciFeeAdapter::class)->apply($order));
            if ($receipt!==null) { $order->update(['status'=>'applied','local_receipt'=>$receipt,'active_key'=>null,'sync_error'=>null]); }
        } catch (ValidationException $error) {
            $order->update(['status'=>'needs_review','sync_error'=>collect($error->errors())->flatten()->first()]);
        }
    }
}

