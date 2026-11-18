<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class FlowPaymentObservation extends Model
{
    public $timestamps=false;
    protected $guarded=['id'];
    protected $casts=['paid_on'=>'date','transfer_on'=>'date','created_at'=>'datetime','gross'=>'integer','reported_fee'=>'integer','net'=>'integer'];
    protected static function booted(): void {
        static::updating(fn()=>throw new \LogicException('Las observaciones financieras son inmutables.'));
        static::deleting(fn()=>throw new \LogicException('Conserva el historial financiero.'));
    }
}
