<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PatientAuditLog extends Model
{
    protected $fillable = [
        'customer_id',
        'patient_id',
        'clinic_branch_id',
        'user_id',
        'action',
        'section',
        'changes',
    ];

    protected $casts = [
        'changes' => 'array',
    ];

    public function patient()
    {
        return $this->belongsTo(Patient::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function clinicBranch()
    {
        return $this->belongsTo(ClinicBranch::class)->withTrashed();
    }
}
