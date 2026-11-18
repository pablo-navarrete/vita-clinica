<?php
namespace App\Http\Controllers\superadmin\Billing;
use App\Http\Controllers\Controller;
use App\Models\PaymentFeeRate;
use App\Services\Billing\PaymentFeeService;
use Illuminate\Http\Request;
class PaymentFeeController extends Controller
{
    public function __construct() { $this->middleware(function($request,$next){
        abort_unless(app(\App\Policies\BillingPlanPolicy::class)->manage($request->user()),403); return $next($request);
    }); }
    public function index(PaymentFeeService $service)
    {
        $client=app(\App\Services\Billing\FlowReadClient::class);
        return view('superadmin.billing.fees.index',['current'=>$service->current(),'rates'=>PaymentFeeRate::orderByDesc('effective_from')->paginate(15),
            'flowConfigured'=>$client->configured(),'flowEnvironment'=>config('flow.environment'),
            'observations'=>\App\Models\FlowPaymentObservation::where('environment',config('flow.environment'))->where('account_hash',$client->accountHash())->orderByDesc('id')->paginate(15,['*'],'flow_page'),
            'alerts'=>\App\Models\PaymentFeeAlert::whereNull('acknowledged_at')->where(function($q)use($client){
                $q->whereNull('environment')->orWhere(fn($q)=>$q->where('environment',config('flow.environment'))->where('account_hash',$client->accountHash()));
            })->orderByDesc('id')->get(),
        ]);
    }
    public function saveDefaults(Request $request, PaymentFeeService $service)
    {
        $data = $request->validate([
            'rate_percent' => ['required', 'numeric', 'between:0,99', 'regex:/^\d{1,3}(\.\d{1,4})?$/'],
            'vat_percent' => ['required', 'numeric', 'between:0,100', 'regex:/^\d{1,3}(\.\d{1,4})?$/'],
        ]);
        $service->saveSimulatorDefaults($request->user(), $data);
        return response()->json(['message' => 'Porcentajes guardados para futuros planes.']);
    }
    public function sync(Request $request,\App\Services\Billing\FlowFeeMonitor $monitor)
    {
        $data=$request->validate(['date'=>'required|date_format:Y-m-d|before_or_equal:'.now('America/Santiago')->toDateString()]);
        try { $count=$monitor->sync($data['date']); }
        catch(\RuntimeException $e) { return back()->with('error',$e->getMessage()); }
        return redirect()->route('superadmin.billing.fees.index')->with('success',"Flow: {$count} pagos revisados. Los registros repetidos no se duplican.");
    }
    public function acknowledge(Request $request,\App\Models\PaymentFeeAlert $alert)
    {
        if(!$alert->acknowledged_at) $alert->update(['acknowledged_at'=>now(),'acknowledged_by'=>$request->user()->id]);
        return redirect()->route('superadmin.billing.fees.index')->with('success','Aviso marcado como revisado. No se modificaron precios.');
    }
    public function store(Request $request,PaymentFeeService $service)
    {
        $data=$request->validate(['scenario'=>'required|string|max:160','effective_from'=>'required|date_format:Y-m-d|after_or_equal:'.now('America/Santiago')->toDateString(),
            'rate_percent'=>['required','numeric','between:0,100','regex:/^\d{1,3}(\.\d{1,4})?$/'],
            'vat_percent'=>['required','numeric','between:0,100','regex:/^\d{1,3}(\.\d{1,4})?$/'],
            'fixed_amount'=>'required|integer|min:0|max:1000000000000','note'=>'required|string|max:2000']);
        $service->publish($request->user(),$data);
        return redirect()->route('superadmin.billing.fees.index')->with('success','Tarifa de comisión registrada.');
    }
}
