<?php
namespace App\Services\Billing;
use App\Models\{BillingPlan, User};
use Illuminate\Support\Facades\DB;
class PlanCatalogService
{
    public function setActive(User $actor, BillingPlan $plan, bool $active): void
    {
        abort_unless(app(\App\Policies\BillingPlanPolicy::class)->manage($actor), 403);
        DB::transaction(function () use ($actor, $plan, $active) {
            $plan = BillingPlan::lockForUpdate()->findOrFail($plan->id);
            if ((bool) $plan->active === $active) return;
            $before = (bool) $plan->active;
            $plan->update(['active' => $active]);
            app(BillingCatalogAuditService::class)->record($actor, $plan, 'plan_status_changed', ['before' => $before, 'active' => $active]);
        });
    }
    public function delete(User $actor, BillingPlan $plan): void
    {
        abort_unless(app(\App\Policies\BillingPlanPolicy::class)->manage($actor), 403);
        DB::transaction(function () use ($actor, $plan) {
            $plan = BillingPlan::lockForUpdate()->findOrFail($plan->id);
            app(BillingCatalogAuditService::class)->record($actor, $plan, 'plan_deleted', ['code' => $plan->code, 'name' => $plan->name]);
            $plan->delete();
        });
    }
    public function save(User $actor, array $data, ?BillingPlan $plan = null): BillingPlan
    {
        abort_unless(app(\App\Policies\BillingPlanPolicy::class)->manage($actor), 403);
        return DB::transaction(function () use ($actor, $data, $plan) {
            $plan = $plan ? BillingPlan::lockForUpdate()->findOrFail($plan->id) : new BillingPlan;
            if ($plan->exists && $plan->code !== $data['code']) {
                throw \Illuminate\Validation\ValidationException::withMessages(['code' => 'El código estable no puede cambiarse.']);
            }
            $before = $plan->getAttributes(); $new = !$plan->exists;
            $plan->fill(collect($data)->only(['code','name','description','active','position'])->all())->save();
            app(BillingCatalogAuditService::class)->record($actor, $plan, $new ? 'plan_created' : 'plan_updated', ['before' => $before, 'after' => $plan->getAttributes()]);
            return $plan;
        });
    }
}
