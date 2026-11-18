<?php
namespace App\Services\Billing;
use App\Models\{BillingPlan, BillingPlanVersion, BillingFeature, User};
use Illuminate\Support\Facades\DB;
class PlanVersionService
{
    private function authorize(User $actor): void { abort_unless(app(\App\Policies\BillingPlanPolicy::class)->manage($actor), 403); }
    public function create(User $actor, BillingPlan $plan, ?BillingPlanVersion $source = null): BillingPlanVersion
    {
        $this->authorize($actor);
        return DB::transaction(function () use ($actor, $plan, $source) {
            $plan = BillingPlan::lockForUpdate()->findOrFail($plan->id);
            if ($source) { $source = BillingPlanVersion::lockForUpdate()->findOrFail($source->id); abort_unless($source->billing_plan_id === $plan->id, 404); }
            $draft = $plan->versions()->where('status','draft')->first();
            if ($draft) return $draft;
            $version = $plan->versions()->create(['number' => ((int) $plan->versions()->max('number')) + 1,
                'status' => 'draft', 'created_by' => $actor->id]);
            if ($source) {
                $version->fill($source->only(['included_branches','trial_days','grace_days','terms']))->save();
                foreach (['prices','limits','features','quotas'] as $relation) foreach ($source->$relation as $row) {
                    $version->$relation()->create(collect($row->getAttributes())->except(['id','billing_plan_version_id'])->all());
                }
            }
            app(BillingCatalogAuditService::class)->record($actor, $plan, 'draft_created', ['copied_from' => $source?->id], $version);
            return $version;
        });
    }
    public function definition(BillingPlanVersion $v): array
    {
        $v->loadMissing(['prices','limits','features.feature','quotas']);
        $data = $v->only(['included_branches','trial_days','grace_days','terms']);
        foreach (['month','year'] as $interval) {
            $price = $v->prices->firstWhere('interval', $interval);
            $data['prices'][$interval] = ['enabled' => $price ? 1 : 0, 'amount' => $price?->amount, 'additional_branch_amount' => $price?->additional_branch_amount];
        }
        foreach ($v->limits as $limit) $data['limits'][$limit->code] = ['unlimited' => $limit->value === null ? 1 : 0, 'value' => $limit->value, 'scope' => $limit->scope];
        $data['features'] = $v->features->map(fn ($row) => $row->feature->code)->all();
        foreach ($v->quotas as $quota) $data['quotas'][$quota->metric] = ['enabled' => 1, 'unlimited' => $quota->quantity === null ? 1 : 0,
            'quantity' => $quota->quantity, 'reset_period' => $quota->reset_period, 'overage_policy' => $quota->overage_policy];
        return $data;
    }
    public function save(User $actor, BillingPlanVersion $version, array $data): void
    {
        $this->authorize($actor); $data = app(PlanDefinitionValidator::class)->validate($data);
        DB::transaction(function () use ($actor, $version, $data) {
            BillingPlan::whereKey($version->billing_plan_id)->lockForUpdate()->firstOrFail();
            $version = BillingPlanVersion::lockForUpdate()->findOrFail($version->id); abort_unless($version->status === 'draft', 409, 'La versión publicada es inmutable.');
            $version->fill(collect($data)->only(['included_branches','trial_days','grace_days','terms'])->all())->save();
            foreach (['prices','limits','features','quotas'] as $relation) $version->$relation()->delete();
            foreach ($data['prices'] as $interval => $price) if ($price['enabled']) $version->prices()->create(['interval' => $interval, 'currency' => 'CLP',
                'amount' => $price['amount'], 'additional_branch_amount' => $price['additional_branch_amount']]);
            foreach ($data['limits'] as $code => $limit) $version->limits()->create(['code' => $code, 'scope' => $limit['scope'], 'value' => $limit['unlimited'] ? null : $limit['value']]);
            foreach ($data['features'] ?? [] as $code) $version->features()->create(['billing_feature_id' => BillingFeature::where('code', $code)->firstOrFail()->id]);
            foreach ($data['quotas'] ?? [] as $metric => $quota) if ($quota['enabled'] ?? false) $version->quotas()->create([
                'metric' => $metric, 'unit' => BillingCapabilities::QUOTAS[$metric]['unit'], 'quantity' => $quota['unlimited'] ? null : $quota['quantity'],
                'reset_period' => $quota['reset_period'], 'overage_policy' => $quota['overage_policy']]);
            app(BillingCatalogAuditService::class)->record($actor, $version->plan, 'draft_updated', ['definition' => $data], $version);
        });
    }
    public function publish(User $actor, BillingPlanVersion $version): void
    {
        $this->authorize($actor);
        DB::transaction(function () use ($actor, $version) {
            BillingPlan::whereKey($version->billing_plan_id)->lockForUpdate()->firstOrFail();
            $version = BillingPlanVersion::lockForUpdate()->findOrFail($version->id); abort_unless($version->status === 'draft', 409);
            app(PlanDefinitionValidator::class)->validate($this->definition($version));
            $snapshot = app(CommercialTermsSerializer::class)->payload($version);
            $version->fill(['status' => 'published', 'snapshot' => $snapshot, 'sha256' => hash('sha256', $snapshot),
                'published_at' => now(), 'published_by' => $actor->id])->save();
            app(BillingCatalogAuditService::class)->record($actor, $version->plan, 'version_published', ['sha256' => $version->sha256], $version);
        });
    }
}
