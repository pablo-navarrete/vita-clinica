<?php
namespace App\Models;
class BillingPlanFeature extends BillingVersionTerm
{
    public function feature() { return $this->belongsTo(BillingFeature::class, 'billing_feature_id'); }
}
