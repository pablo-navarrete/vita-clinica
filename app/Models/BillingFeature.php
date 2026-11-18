<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class BillingFeature extends Model
{
    public $timestamps = false;
    protected $guarded = ['id'];
    protected static function booted(): void
    {
        static::saving(function ($model) {
            if (!isset(\App\Services\Billing\BillingCapabilities::FEATURES[$model->code])) throw new \LogicException('Funcionalidad no reconocida.');
        });
    }
}
