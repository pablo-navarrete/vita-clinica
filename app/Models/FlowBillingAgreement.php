<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FlowBillingAgreement extends Model
{
    public $incrementing = false;

    protected $keyType = 'string';

    protected $guarded = ['open_subscription'];

    protected $casts = ['quote' => 'array', 'starts_on' => 'date', 'accepted_at' => 'datetime', 'stopped_at' => 'datetime'];

    public function billingCustomer()
    {
        return $this->belongsTo(FlowBillingCustomer::class, 'flow_billing_customer_id');
    }

    public function subscription()
    {
        return $this->belongsTo(CustomerSubscription::class, 'customer_subscription_id');
    }
}
