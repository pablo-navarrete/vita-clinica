<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class BillingPlanVersion extends Model
{
    protected $guarded = ['id'];
    protected $casts = ['published_at' => 'datetime'];
    public function plan() { return $this->belongsTo(BillingPlan::class, 'billing_plan_id')->withTrashed(); }
    public function prices() { return $this->hasMany(BillingPlanPrice::class); }
    public function limits() { return $this->hasMany(BillingPlanLimit::class); }
    public function features() { return $this->hasMany(BillingPlanFeature::class); }
    public function quotas() { return $this->hasMany(BillingPlanQuota::class); }
    protected static function booted(): void
    {
        static::updating(function ($model) {
            if ($model->getRawOriginal('status') !== 'draft' || $model->isDirty(['billing_plan_id','number'])) throw new \LogicException('La versión publicada es inmutable.');
        });
        static::deleting(function ($model) { if ($model->status !== 'draft') throw new \LogicException('La versión publicada debe conservarse.'); });
    }
}
