<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
abstract class BillingVersionTerm extends Model
{
    public $timestamps = false;
    protected $guarded = ['id'];
    public function version() { return $this->belongsTo(BillingPlanVersion::class, 'billing_plan_version_id'); }
    protected static function booted(): void
    {
        $guard = function ($model) {
            $id = $model->getRawOriginal('billing_plan_version_id') ?? $model->billing_plan_version_id;
            if (BillingPlanVersion::findOrFail($id)->status !== 'draft' || ($model->exists && $model->isDirty('billing_plan_version_id'))) {
                throw new \LogicException('Las condiciones publicadas son inmutables.');
            }
        };
        static::saving($guard); static::deleting($guard);
    }
}
