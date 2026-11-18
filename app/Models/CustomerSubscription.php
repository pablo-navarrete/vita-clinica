<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class CustomerSubscription extends Model
{
    protected $guarded=['id'];
    protected $casts=['starts_on'=>'date','trial_ends_on'=>'date','paid_through_on'=>'date','next_renewal_on'=>'date',
        'access_ends_on'=>'date','cancelled_at'=>'datetime','cancel_at_period_end'=>'boolean'];
    public function customer(){ return $this->belongsTo(Customer::class)->withTrashed(); }
    public function price(){ return $this->belongsTo(BillingPlanPrice::class,'billing_plan_price_id'); }
    public function payments(){ return $this->hasMany(SubscriptionPayment::class); }
    public function events(){ return $this->hasMany(SubscriptionEvent::class); }
    public function changes(){ return $this->hasMany(SubscriptionChange::class); }
    public function pendingChange(){ return $this->hasOne(SubscriptionChange::class)->where('status','pending'); }
    public function openBillingAgreement(){ return $this->hasOne(FlowBillingAgreement::class)->whereIn('state',['creating','active','ending','review']); }
}
