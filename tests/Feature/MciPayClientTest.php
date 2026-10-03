<?php
namespace Tests\Feature;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use App\Models\MciPayOrder;
use App\Services\{MciPayClient,MciFeeAdapter};
use Illuminate\Validation\ValidationException;
class MciPayClientTest extends TestCase
{
 use RefreshDatabase;
 protected function setUp():void {parent::setUp();config(['app.key'=>'base64:'.base64_encode(str_repeat('t',32)),'mci_pay.secret'=>str_repeat('s',64),'mci_pay.enabled'=>true]);}
 private function order():MciPayOrder { return MciPayOrder::create(['id'=>(string)Str::uuid(),'principal_type'=>'student','principal_id'=>'1','active_key'=>(string)Str::uuid(),'student_reference'=>'ST-001','payer_name'=>'Test','purpose'=>'Monthly fee','amount_paise'=>50000,'metadata'=>[]]); }
 private function data(MciPayOrder $o,string $status='pending',int $revision=1):array {return ['order_id'=>$o->id,'central_id'=>$o->central_id??(string)Str::uuid(),'amount_paise'=>50000,'currency'=>'INR','revision'=>$revision,'status'=>$status,'reference'=>'123456789012','payment_date'=>today()->toDateString(),'receipt_no'=>$status==='verified'?'CENTRAL-001':null,'checkout_url'=>'https://pay.mciedu.com/pay/upi/'.str_repeat('a',64)];}
 private function sendCallback(array $data,bool $valid=true) { $body=json_encode($data);$time=(string)time();return $this->call('POST','/mci-pay/callback',[],[],[],['CONTENT_TYPE'=>'application/json','HTTP_ACCEPT'=>'application/json','HTTP_X_MCI_INTEGRATION'=>config('mci_pay.integration'),'HTTP_X_MCI_TIMESTAMP'=>$time,'HTTP_X_MCI_SIGNATURE'=>$valid?MciPayClient::sign(str_repeat('s',64),'/mci-pay/callback',$time,$body):str_repeat('0',64)],$body); }
 public function test_pending_callback_never_posts_fee_and_tampering_is_rejected():void { $o=$this->order();$this->mock(MciFeeAdapter::class)->shouldNotReceive('apply');$this->sendCallback($this->data($o),false)->assertUnauthorized();$this->sendCallback($this->data($o))->assertOk();$this->assertSame('pending',$o->fresh()->status);$this->assertNull($o->fresh()->local_receipt); }
 public function test_verified_callback_posts_once_and_delayed_pending_cannot_downgrade():void { $o=$this->order();$data=$this->data($o,'verified',2);$this->mock(MciFeeAdapter::class)->shouldReceive('apply')->once()->andReturn('LOCAL-001');$this->sendCallback($data)->assertOk();$this->sendCallback($data)->assertOk();$this->sendCallback(array_replace($data,['status'=>'pending','revision'=>1]))->assertOk();$this->assertSame('applied',$o->fresh()->status);$this->assertSame('LOCAL-001',$o->fresh()->local_receipt);$this->assertNull($o->fresh()->active_key); }
 public function test_amount_mismatch_never_posts_fee():void { $o=$this->order();$this->mock(MciFeeAdapter::class)->shouldNotReceive('apply');$this->sendCallback(array_replace($this->data($o,'verified'),['amount_paise'=>1]))->assertStatus(422);$this->sendCallback(array_replace($this->data($o,'verified'),['amount_paise'=>40000]))->assertStatus(409);$this->assertSame('created',$o->fresh()->status); }
 public function test_overpayment_is_kept_for_office_review_without_marking_paid():void { $o=$this->order();$this->mock(MciFeeAdapter::class)->shouldReceive('apply')->once()->andThrow(ValidationException::withMessages(['payment'=>'Balance changed']));$this->sendCallback($this->data($o,'verified',2))->assertOk();$this->assertSame('needs_review',$o->fresh()->status);$this->assertNull($o->fresh()->local_receipt); }
 public function test_guest_cannot_view_someone_elses_order():void { $this->get(route('mci-pay.show',$this->order()))->assertRedirect(); }
}
