<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Documentary association only. Never an authorization to clinical data. */
class PortalPatientLink extends Model
{
    protected $guarded = ['*'];
    protected $hidden = ['account_reference','patient_reference'];
    protected $casts = ['clinical_access'=>'boolean'];

    public function account() { return $this->belongsTo(PortalAccount::class, 'portal_account_id'); }
    public function patient() { return $this->belongsTo(Patient::class); }
}
