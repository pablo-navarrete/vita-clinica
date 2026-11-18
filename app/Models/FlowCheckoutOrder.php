<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FlowCheckoutOrder extends Model
{
    public $incrementing = false;

    protected $keyType = 'string';

    protected $guarded = ['open_subscription'];

    protected $casts = ['quote' => 'array', 'token' => 'encrypted', 'redirect_url' => 'encrypted', 'period_start' => 'date', 'paid_at' => 'datetime'];

    protected $hidden = ['token', 'redirect_url'];

    public function subscription()
    {
        return $this->belongsTo(CustomerSubscription::class, 'customer_subscription_id');
    }
}
