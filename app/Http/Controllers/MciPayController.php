<?php
namespace App\Http\Controllers;

use App\Models\MciPayOrder;
use App\Services\MciFeeAdapter;
use App\Services\MciPayClient;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class MciPayController extends Controller
{
    public function access() { abort_unless(config('mci_pay.enabled'),404); return view('mci-pay.access'); }
    public function authenticate(Request $request, MciFeeAdapter $fees) { abort_unless(config('mci_pay.enabled'),404); $fees->admissionAccess($request); return redirect()->route('mci-pay.index'); }
    public function index(Request $request,MciFeeAdapter $fees)
    {
        abort_unless(config('mci_pay.enabled'),404);
        return response()->view('mci-pay.index',$fees->page($request))->header('Cache-Control','private, no-store');
    }
    public function store(Request $request,MciFeeAdapter $fees,MciPayClient $client)
    {
        abort_unless(config('mci_pay.enabled'),404);
        $order=$client->create($fees->quote($request));
        return redirect()->away($order->checkout_url);
    }
    public function show(Request $request,MciPayOrder $order,MciFeeAdapter $fees)
    {
        $fees->authorize($request,$order);
        return response()->view('mci-pay.show',compact('order'))->header('Cache-Control','private, no-store')->header('Referrer-Policy','no-referrer');
    }
    public function refresh(Request $request,MciPayOrder $order,MciFeeAdapter $fees,MciPayClient $client)
    {
        $fees->authorize($request,$order);$client->refresh($order);
        return back()->with('success','भुगतान की स्थिति अपडेट हो गई है।');
    }
    public function callback(Request $request,MciPayClient $client)
    {
        $client->authenticateCallback($request);
        $request->validate(['order_id'=>['required','uuid']]);
        $order=MciPayOrder::findOrFail($request->input('order_id'));
        $client->applySnapshot($order,$request->json()->all());
        return response()->json(['ok'=>true]);
    }
    public function admin(Request $request,MciFeeAdapter $fees)
    {
        $q=$fees->adminQuery($request);
        $data=$request->validate(['search'=>['nullable','string','max:100'],'status'=>['nullable','in:created,pending,verified,rejected,applied,needs_review']]);
        if (!empty($data['search'])) { $s='%'.$data['search'].'%';$q->where(fn($x)=>$x->where('payer_name','like',$s)->orWhere('student_reference','like',$s)->orWhere('reference','like',$s)); }
        if (!empty($data['status'])) { $q->where('status',$data['status']); }
        return response()->view('mci-pay.admin',['orders'=>$q->latest()->paginate(30)->withQueryString()])->header('Cache-Control','private, no-store');
    }
}

