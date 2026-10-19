<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Encounter extends Model
{
    use HasFactory;

    protected $casts = [
        'started_at' => 'datetime',
        'ended_at' => 'datetime',
    ];

    protected $fillable = [
        'patient_id',
        'appointment_id',
        'professional_id',
        'specialty_id',
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
}
