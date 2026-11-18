<?php
namespace App\Http\Controllers\superadmin\Billing;
use App\Http\Controllers\Controller;
use App\Http\Requests\Billing\PlanRequest;
use App\Models\{BillingPlan, BillingCatalogEvent};
use App\Services\Billing\PlanCatalogService;
use Illuminate\Http\Request;
class PlanController extends Controller
{
    public function __construct() { $this->middleware(function ($request, $next) {
        abort_unless(app(\App\Policies\BillingPlanPolicy::class)->manage($request->user()), 403); return $next($request);
    }); }
    public function index(Request $request)
    {
        $search = mb_substr((string) $request->query('search', ''), 0, 255);
        return view('superadmin.billing.plans.index', compact('search'));
    }
    public function data(Request $request)
    {
        $request->validate(['length' => 'sometimes|integer|min:1|max:100', 'start' => 'sometimes|integer|min:0',
            'search.value' => 'nullable|string|max:255']);
        $relations=['prices','limits','features.feature','quotas'];
        return \Yajra\DataTables\Facades\DataTables::eloquent(BillingPlan::withCount('versions')->with([
            'currentVersion'=>fn($q)=>$q->with($relations), 'workingVersion'=>fn($q)=>$q->with($relations)]))
            ->filter(function ($query) use ($request) {
                $search = trim((string) $request->input('search.value', ''));
                if ($search !== '') $query->where(fn ($q) => $q->where('name','like',"%$search%")->orWhere('code','like',"%$search%"));
            })
            ->addColumn('show_url', fn ($plan) => route('superadmin.billing.plans.show', $plan))
            ->addColumn('edit_url', fn ($plan) => route('superadmin.billing.plans.edit', $plan))
            ->addColumn('delete_url', fn ($plan) => route('superadmin.billing.plans.destroy', $plan))
            ->addColumn('status_url', fn ($plan) => route('superadmin.billing.plans.status', $plan))
            ->addColumn('commercial',fn($plan)=>app(\App\Services\Billing\PlanEditingService::class)->data($plan))
            ->escapeColumns([])->toJson();
    }
    public function status(Request $request, BillingPlan $plan, PlanCatalogService $service)
    {
        $data = $request->validate(['active' => 'required|boolean']);
        $service->setActive($request->user(), $plan, (bool) $data['active']);
        return response()->json(['message' => 'Estado del plan actualizado.']);
    }
    public function create(Request $request) {
        $plan = new BillingPlan;
        $definition=[]; $revision=0;
        if ($request->expectsJson()) return response()->json(['title'=>'Crear plan', 'html'=>view('superadmin.billing.partials.plan-form',compact('plan','definition','revision'))->render()])->header('Cache-Control','no-store');
        return view('superadmin.billing.plans.form', compact('plan','definition','revision'));
    }
    public function destroy(Request $request, BillingPlan $plan, PlanCatalogService $service)
    {
        $service->delete($request->user(), $plan);
        return response()->json(['message' => 'Plan eliminado del catálogo. Su historial se conserva.']);
    }
    public function edit(Request $request, BillingPlan $plan) {
        ['definition'=>$definition,'revision'=>$revision]=app(\App\Services\Billing\PlanEditingService::class)->data($plan);
        if ($request->expectsJson()) return response()->json(['title'=>'Editar plan', 'html'=>view('superadmin.billing.partials.plan-form',compact('plan','definition','revision'))->render()])->header('Cache-Control','no-store');
        return view('superadmin.billing.plans.form', compact('plan','definition','revision'));
    }
    public function store(PlanRequest $request, PlanCatalogService $service)
    {
        $data=$request->validated();
        $plan = isset($data['definition'])
            ? app(\App\Services\Billing\PlanEditingService::class)->save($request->user(),$data,$data['definition'],null,(int)$data['revision'])
            : $service->save($request->user(), $data);
        if ($request->expectsJson()) return response()->json(['message'=>'Plan creado correctamente.','id'=>$plan->id]);
        return redirect()->route('superadmin.billing.plans.show', $plan)->with('success','Plan creado correctamente.');
    }
    public function update(PlanRequest $request, BillingPlan $plan, PlanCatalogService $service)
    {
        $data=$request->validated();
        if (isset($data['definition'])) app(\App\Services\Billing\PlanEditingService::class)->save($request->user(),$data,$data['definition'],$plan,(int)$data['revision']);
        else $service->save($request->user(),$data,$plan);
        if ($request->expectsJson()) return response()->json(['message'=>'Plan actualizado correctamente.']);
        return redirect()->route('superadmin.billing.plans.show', $plan)->with('success','Plan actualizado. Sus versiones publicadas se conservaron.');
    }
    public function show(Request $request, BillingPlan $plan)
    {
        $versions = $plan->versions()->get();
        $events = BillingCatalogEvent::where('billing_plan_id', $plan->id)->orderByDesc('id')->paginate(15);
        $current=$plan->currentVersion; $terms=null;
        if ($current) {
            abort_unless(hash_equals($current->sha256,hash('sha256',$current->snapshot)),409,'No se pudo verificar la integridad del plan.');
            $terms=json_decode($current->snapshot,true,512,JSON_THROW_ON_ERROR);
        }
        if ($request->expectsJson()) return response()->json(['title'=>'Ver plan: '.$plan->name,
            'html'=>view('superadmin.billing.partials.plan-detail',compact('plan','versions','events','terms'))->render()])->header('Cache-Control','no-store');
        return view('superadmin.billing.plans.show', compact('plan','versions','events','terms'));
    }
}
