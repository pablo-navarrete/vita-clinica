<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class PaymentFeeRate extends Model
{
    public $timestamps=false;
    protected $guarded=['id'];
    protected $casts=['effective_from'=>'date','created_at'=>'datetime','rate_units'=>'integer','vat_units'=>'integer','fixed_amount'=>'integer'];
    protected static function booted(): void
    {
        static::updating(fn()=>throw new \LogicException('Las tarifas publicadas son inmutables.'));
        static::deleting(fn()=>throw new \LogicException('El historial de tarifas debe conservarse.'));
    }
}
