<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PortalClinicalGrant extends Model
{
    public $incrementing = false;
    protected $keyType = 'string';
    protected $guarded = ['active_pair'];
    protected $hidden = ['identity_hash','account_reference','patient_reference'];
    protected $casts = ['approved_at'=>'datetime','revoked_at'=>'datetime'];

    public function customer() { return $this->belongsTo(Customer::class)->withTrashed(); }
    public function patient() { return $this->belongsTo(Patient::class); }
    public function account() { return $this->belongsTo(PortalAccount::class,'portal_account_id'); }
    public function link() { return $this->belongsTo(PortalPatientLink::class,'link_id'); }
    public function approver() { return $this->belongsTo(User::class,'approved_by')->withTrashed(); }
}
