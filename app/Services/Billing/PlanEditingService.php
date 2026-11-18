<?php
namespace App\Services\Billing;
use App\Models\{BillingPlan, User};
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Simple plan editing UI; immutable versions remain an internal implementation detail. */
class PlanEditingService
{
    public function save(User $actor, array $metadata, array $definition, ?BillingPlan $plan, int $expectedVersion): BillingPlan
    {
        abort_unless(app(\App\Policies\BillingPlanPolicy::class)->manage($actor),403);
        app(PlanDefinitionValidator::class)->validate($definition);
        return DB::transaction(function () use ($actor,$metadata,$definition,$plan,$expectedVersion) {
            if ($plan) {
                $plan=BillingPlan::lockForUpdate()->findOrFail($plan->id);
                $latest=(int)$plan->versions()->where('status','published')->max('number');
                if ($latest !== $expectedVersion) throw ValidationException::withMessages(['plan'=>'Otro administrador modificó el plan. Cierra y vuelve a abrir para revisar las condiciones actuales.']);
            }
            $plan=app(PlanCatalogService::class)->save($actor,$metadata,$plan);
            $versions=app(PlanVersionService::class);
            $draft=$versions->create($actor,$plan);
            $versions->save($actor,$draft,$definition);
            $versions->publish($actor,$draft);
            return $plan;
        });
    }
    public function data(BillingPlan $plan): array
    {
        $version=$plan->currentVersion ?? $plan->workingVersion;
        return ['definition'=>$version ? app(PlanVersionService::class)->definition($version) : [],
            'revision'=>(int)($plan->currentVersion?->number ?? 0)];
    }
}
