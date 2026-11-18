<?php
namespace App\Http\Controllers\superadmin\Billing;
use App\Http\Controllers\Controller;
use App\Http\Requests\Billing\VersionRequest;
use App\Models\{BillingPlan, BillingPlanVersion};
use App\Services\Billing\{PlanVersionService, BillingCapabilities};
use Illuminate\Http\Request;
class PlanVersionController extends Controller
{
    public function __construct() { $this->middleware(function ($request, $next) {
        abort_unless(app(\App\Policies\BillingPlanPolicy::class)->manage($request->user()), 403); return $next($request);
    }); }
    public function store(Request $request, BillingPlan $plan, PlanVersionService $service)
    {
        $data = $request->validate(['source_id' => 'nullable|integer']);
        $source = isset($data['source_id']) ? $plan->versions()->findOrFail($data['source_id']) : null;
        $version = $service->create($request->user(), $plan, $source);
        return redirect()->route('superadmin.billing.versions.edit', $version)->with('success','Borrador creado.');
    }
    public function edit(BillingPlanVersion $version, PlanVersionService $service)
    {
        abort_unless($version->status === 'draft', 409, 'Las versiones publicadas son de consulta.');
        return view('superadmin.billing.versions.edit', ['version' => $version, 'definition' => $service->definition($version),
            'features' => BillingCapabilities::FEATURES, 'limits' => BillingCapabilities::LIMITS, 'quotas' => BillingCapabilities::QUOTAS]);
    }
    public function update(VersionRequest $request, BillingPlanVersion $version, PlanVersionService $service)
    {
        $service->save($request->user(), $version, $request->validated());
        return redirect()->route('superadmin.billing.versions.show', $version)->with('success','Borrador guardado. Revisa sus condiciones antes de publicar.');
    }
    public function show(BillingPlanVersion $version, PlanVersionService $service)
    {
        if ($version->status === 'published') {
            abort_unless(hash_equals($version->sha256, hash('sha256', $version->snapshot)), 409, 'La versión no supera la verificación de integridad.');
            $terms = json_decode($version->snapshot, true, 512, JSON_THROW_ON_ERROR);
        } else {
            $version->load(['prices','limits','features.feature','quotas']);
            $terms = ['plan_name' => $version->plan->name, 'included_branches' => $version->included_branches,
                'trial_days' => $version->trial_days, 'grace_days' => $version->grace_days, 'terms' => $version->terms,
                'prices' => $version->prices->toArray(), 'limits' => $version->limits->toArray(),
                'features' => $version->features->map(fn ($row) => $row->feature->code)->all(), 'quotas' => $version->quotas->toArray()];
        }
        return view('superadmin.billing.versions.show', compact('version','terms'));
    }
    public function publish(Request $request, BillingPlanVersion $version, PlanVersionService $service)
    {
        $request->validate(['confirmation' => 'accepted']);
        $service->publish($request->user(), $version);
        return redirect()->route('superadmin.billing.versions.show', $version)->with('success','Versión publicada. Las condiciones quedaron inmutables.');
    }
}
