<?php
namespace App\Services\Billing;
use App\Models\{BillingCatalogEvent, BillingPlan, BillingPlanVersion, User};
class BillingCatalogAuditService
{
    public function record(User $actor, BillingPlan $plan, string $action, array $details, ?BillingPlanVersion $version = null): void
    {
        BillingCatalogEvent::create(['billing_plan_id' => $plan->id, 'billing_plan_version_id' => $version?->id,
            'actor_id' => $actor->id, 'action' => $action, 'details' => $details, 'created_at' => now()]);
    }
}
