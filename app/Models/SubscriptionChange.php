<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class SubscriptionChange extends Model
{
    protected $guarded=['id','pending_subscription'];
    protected $casts=['effective_on'=>'date','applied_at'=>'datetime'];
    public function price(){return $this->belongsTo(BillingPlanPrice::class,'billing_plan_price_id');}
}
