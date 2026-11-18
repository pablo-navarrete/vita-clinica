<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class SubscriptionPayment extends Model
{
    public $timestamps=false;
    protected $guarded=['id'];
    protected $casts=['breakdown'=>'array','period_start'=>'date','period_end'=>'date','paid_on'=>'date','created_at'=>'datetime'];
    public function price(){return $this->belongsTo(BillingPlanPrice::class,'billing_plan_price_id');}
    protected static function booted(): void {
        static::updating(fn()=>throw new \LogicException('Los pagos registrados son inmutables.'));
        static::deleting(fn()=>throw new \LogicException('El historial de pagos debe conservarse.'));
    }
}
