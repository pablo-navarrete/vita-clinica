<?php
namespace App\Http\Controllers\superadmin\Billing;
use App\Http\Controllers\Controller;
use App\Http\Requests\Billing\SubscriptionRequest;
use App\Models\{CustomerSubscription,Customer,BillingPlan};
use App\Services\Billing\SubscriptionService;
use Illuminate\Http\Request;
class SubscriptionController extends Controller
{
    public function __construct(){ $this->middleware(function($request,$next){
        abort_unless(app(\App\Policies\BillingPlanPolicy::class)->manage($request->user()),403); return $next($request);
    }); }
    public function index(){
        return view('superadmin.billing.subscriptions.index',[
            'customers'=>Customer::whereNotIn('id',CustomerSubscription::select('customer_id'))->orderBy('name')->get(['id','name']),
            'options'=>$this->options(),
        ]);
    }
    public function data(Request $request,SubscriptionService $service)
    {
        $request->validate(['length'=>'sometimes|integer|min:1|max:100','start'=>'sometimes|integer|min:0','search.value'=>'nullable|string|max:255']);
        return \Yajra\DataTables\Facades\DataTables::eloquent(CustomerSubscription::with(['customer','price.version.plan','openBillingAgreement']))
            ->filter(function($q)use($request){ $search=trim((string)$request->input('search.value','')); if($search!=='')$q->whereHas('customer',fn($q)=>$q->where('name','like',"%$search%")); })
            ->addColumn('customer_name',fn($s)=>$s->customer->name)
            ->addColumn('plan_name',fn($s)=>$service->quote($s->price,(int)$s->branch_quantity)['plan_name'])
            ->addColumn('billing_interval',fn($s)=>$s->price->interval==='month'?'Mensual':'Anual')
            ->addColumn('state_label',fn($s)=>SubscriptionService::STATES[$service->state($s)])
            ->addColumn('payment_mode_label',fn($s)=>$service->classification($s)['payment_mode_label'])
            ->addColumn('contract_label',fn($s)=>$service->classification($s)['contract_label'])
            ->addColumn('financial_label',fn($s)=>$service->classification($s)['financial_label'])
            ->addColumn('access_label',fn($s)=>$service->classification($s)['access_label'])
            ->addColumn('paid_until',fn($s)=>$s->paid_through_on?->format('d/m/Y') ?? 'Sin pagos registrados')
            ->addColumn('next_date',fn($s)=>$s->cancel_at_period_end?'—':$s->next_renewal_on->format('d/m/Y'))
            ->addColumn('show_url',fn($s)=>route('superadmin.billing.subscriptions.show',$s))
            ->escapeColumns([])->toJson();
    }
    private function options(): array
    {
        $options=[];
        foreach(BillingPlan::where('active',true)->with('currentVersion.prices')->orderBy('position')->get() as $plan) {
            if(!$plan->currentVersion) continue;
            foreach($plan->currentVersion->prices as $price) $options[]=['id'=>$price->id,'name'=>$plan->name,
                'interval'=>$price->interval,'amount'=>$price->amount,'additional'=>$price->additional_branch_amount,
                'included'=>$plan->currentVersion->included_branches,'trial'=>$plan->currentVersion->trial_days];
        }
        return $options;
    }
    public function create()
    {
        $customers=Customer::whereNotIn('id',CustomerSubscription::select('customer_id'))->orderBy('name')->get(['id','name']);
        return view('superadmin.billing.subscriptions.create',['customers'=>$customers,'options'=>$this->options()]);
    }
    public function store(SubscriptionRequest $request,SubscriptionService $service)
    {
        $s=$service->create($request->user(),$request->validated());
        if($request->expectsJson()) return response()->json(['message'=>'Suscripción creada correctamente.','customer_id'=>$s->customer_id],201);
        return redirect()->route('superadmin.billing.subscriptions.show',$s)->with('success','Suscripción registrada localmente. No se realizó ningún cobro.');
    }
    public function show(CustomerSubscription $subscription,SubscriptionService $service)
    {
        $subscription->load(['customer','price.version.plan','pendingChange.price.version','openBillingAgreement']);
        return view('superadmin.billing.subscriptions.show',['subscription'=>$subscription,'state'=>SubscriptionService::STATES[$service->state($subscription)],
            'classification'=>$service->classification($subscription),
            'flowOrders'=>\App\Models\FlowCheckoutOrder::where('customer_subscription_id',$subscription->id)->orderByDesc('created_at')->paginate(10,['id','state','quote','created_at','paid_at'],'orders_page'),
            'upcoming'=>$service->upcoming($subscription),'quote'=>$service->quote($subscription->price,(int)$subscription->branch_quantity),
            'options'=>$this->options(),'payments'=>$subscription->payments()->orderByDesc('period_start')->get(),
            'events'=>$subscription->events()->with('actor')->orderByDesc('id')->paginate(15)]);
    }
    public function payment(Request $request,CustomerSubscription $subscription,SubscriptionService $service)
    {
        $data=$request->validate(['revision'=>'required|integer|min:1','paid_on'=>'required|date_format:Y-m-d|before_or_equal:today',
            'amount'=>'required|integer|min:0|max:9007199254740991','reference'=>'required|string|max:120','note'=>'required|string|max:2000']);
        $service->recordPayment($request->user(),$subscription,$data);
        return redirect()->route('superadmin.billing.subscriptions.show',$subscription)->with('success','Pago manual registrado. El período pagado se actualizó.');
    }
    public function change(Request $request,CustomerSubscription $subscription,SubscriptionService $service)
    {
        $data=$request->validate(['revision'=>'required|integer|min:1','billing_plan_price_id'=>'required|integer|exists:billing_plan_prices,id',
            'branch_quantity'=>'required|integer|min:1|max:2147483647','reason'=>'required|string|max:2000']);
        $service->scheduleChange($request->user(),$subscription,$data);
        return redirect()->route('superadmin.billing.subscriptions.show',$subscription)->with('success','Cambio programado para el próximo período. No se envió ningún cobro.');
    }
    public function cancelChange(Request $request,CustomerSubscription $subscription,SubscriptionService $service)
    {
        $data=$request->validate(['revision'=>'required|integer|min:1']);
        $service->cancelChange($request->user(),$subscription,(int)$data['revision']);
        return redirect()->route('superadmin.billing.subscriptions.show',$subscription)->with('success','Cambio pendiente cancelado.');
    }
    public function cancel(Request $request,CustomerSubscription $subscription,SubscriptionService $service)
    {
        $data=$request->validate(['revision'=>'required|integer|min:1','reason'=>'required|string|max:2000','confirmation'=>'accepted']);
        $service->cancel($request->user(),$subscription,$data);
        return redirect()->route('superadmin.billing.subscriptions.show',$subscription)->with('success','Cancelación local programada. El período pagado se conserva.');
    }
}
