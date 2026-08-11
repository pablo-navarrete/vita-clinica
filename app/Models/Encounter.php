<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Encounter extends Model
{
    use HasFactory;
    protected $fillable = [
        'patient_id',
        'appointment_id',
        'status',
        'chief_complaint',
        'started_at',
        'ended_at',
        'customer_id'
      ];

    public function patient()
    {
        return $this->belongsTo(Patient::class);
    }
}
