<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Encounter extends Model
{
    use HasFactory;

    protected static function booted(): void
    {
        static::creating(function (Encounter $encounter) {
            if (!$encounter->clinic_branch_id || !ClinicBranch::query()
                ->where('customer_id', $encounter->customer_id)
                ->where('status', true)->whereKey($encounter->clinic_branch_id)->exists()) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'encounter' => 'La atención debe tener una sede activa de la clínica.',
                ]);
            }
        });
    }

    protected $casts = [
        'started_at' => 'datetime',
        'ended_at' => 'datetime',
    ];

    protected $fillable = [
        'clinic_branch_id',
        'patient_id',
        'appointment_id',
        'professional_id',
        'specialty_id',
        'modality',
        'telemedicine_session_id',
        'opened_by',
        'created_by',
        'status',
        'chief_complaint',
        'clinical_assessment',
        'treatment_plan',
        'cancellation_reason',
        'started_at',
        'ended_at',
        'customer_id'
      ];

    public function patient()
    {
        return $this->belongsTo(Patient::class);
    }

    public function clinicBranch()
    {
        return $this->belongsTo(ClinicBranch::class)->withTrashed();
    }

    public function appointment()
    {
        return $this->belongsTo(Appointment::class);
    }

    public function professional()
    {
        return $this->belongsTo(User::class, 'professional_id');
    }

    public function specialty()
    {
        return $this->belongsTo(Specialty::class);
    }

    public function openedBy()
    {
        return $this->belongsTo(User::class, 'opened_by');
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function telemedicineSession()
    {
        return $this->belongsTo(TelemedicineSession::class);
    }

    public function documents()
    {
        return $this->hasMany(PatientDocument::class);
    }

    public function signatures()
    {
        return $this->hasMany(EncounterSignature::class);
    }

    public function signature()
    {
        return $this->hasOne(EncounterSignature::class)->whereHas('documentVersion', fn ($q) => $q->where('kind', 'original'));
    }

    public function addendumSignatures()
    {
        return $this->hasMany(EncounterSignature::class)->whereHas('documentVersion', fn ($q) => $q->where('kind', 'addendum'))
            ->orderBy('document_version_id');
    }

    public function observations()
    {
        return $this->hasMany(EncounterObservation::class)->orderBy('created_at');
    }
}
