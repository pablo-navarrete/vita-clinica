<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FlowCardRegistration extends Model
{
    public $incrementing = false;

    protected $keyType = 'string';

    protected $guarded = [];

    protected $casts = ['token' => 'encrypted', 'redirect_url' => 'encrypted'];

    protected $hidden = ['token', 'token_hash', 'redirect_url'];

    public function billingCustomer()
    {
        return $this->belongsTo(FlowBillingCustomer::class, 'flow_billing_customer_id');
    }
}
