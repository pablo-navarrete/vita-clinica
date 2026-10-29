<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TelemedicineSession extends Model
{
    protected $fillable = [
        'customer_id',
        'appointment_id',
        'patient_id',
        'professional_id',
        'clinic_branch_id',
        'specialty_id',
        'expires_at',
        'room_name',
        'created_by',
        'status',
        'patient_consent_at',
        'patient_consent_version',
        'patient_consent_text',
        'patient_invitation_sent_at',
        'patient_whatsapp_invitation_sent_at',
        'started_at',
        'ended_at',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'patient_consent_at' => 'datetime',
        'patient_invitation_sent_at' => 'datetime',
        'patient_whatsapp_invitation_sent_at' => 'datetime',
        'started_at' => 'datetime',
        'ended_at' => 'datetime',
    ];

    public function appointment()
    {
        return $this->belongsTo(Appointment::class);
    }

    public function patient() { return $this->belongsTo(Patient::class); }
    public function professional() { return $this->belongsTo(User::class, 'professional_id'); }
    public function specialty() { return $this->belongsTo(Specialty::class); }
    public function clinicBranch() { return $this->belongsTo(ClinicBranch::class)->withTrashed(); }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function encounter()
    {
        return $this->hasOne(Encounter::class);
    }
}
