<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class PaymentFeeAlert extends Model
{
    public $timestamps=false;
    protected $guarded=['id'];
    protected $casts=['details'=>'array','created_at'=>'datetime','acknowledged_at'=>'datetime','emailed_at'=>'datetime'];
}
