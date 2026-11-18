<?php
namespace App\Services\Billing;
use App\Models\BillingPlanVersion;
class CommercialTermsSerializer
{
    public function payload(BillingPlanVersion $version): string
    {
        $version->load(['plan','prices','limits','features.feature','quotas']);
        return json_encode([
            'schema' => 'vita-commercial-v1', 'plan_code' => $version->plan->code, 'plan_name' => $version->plan->name,
            'plan_description' => $version->plan->description, 'version' => (int) $version->number,
            'included_branches' => (int) $version->included_branches, 'trial_days' => (int) $version->trial_days,
            'grace_days' => (int) $version->grace_days, 'terms' => $version->terms,
            'prices' => $version->prices->sortBy('interval')->values()->map->only(['interval','currency','amount','additional_branch_amount'])->all(),
            'limits' => $version->limits->sortBy('code')->values()->map->only(['code','scope','value'])->all(),
            'features' => $version->features->map(fn ($row) => $row->feature->code)->sort()->values()->all(),
            'quotas' => $version->quotas->sortBy('metric')->values()->map->only(['metric','unit','quantity','reset_period','overage_policy'])->all(),
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    }
}
