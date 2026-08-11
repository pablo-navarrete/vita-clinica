<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Appointment extends Model
{
    use HasFactory;
       protected $fillable = [
        'customer_id',
        'patient_id',
        'professional_id',
        'specialty_id',
        'start_at',
        'end_at',
        'status',
        'reason',
        'notes',
    ];



    // Estados
    const SCHEDULED   = 1;
    const CONFIRMED   = 2;
    const IN_PROGRESS = 3;
    const COMPLETED   = 4;
    const CANCELLED   = 5;
    const NO_SHOW     = 6;

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function patient()
    {
        return $this->belongsTo(Patient::class);
    }

    public function professional()
    {
        return $this->belongsTo(User::class, 'professional_id');
    }

    public function getStatusTextAttribute()
    {
        return match ($this->status) {
            self::SCHEDULED   => 'Agendada',
            self::CONFIRMED   => 'Confirmada',
            self::IN_PROGRESS => 'En atención',
            self::COMPLETED   => 'Finalizada',
            self::CANCELLED   => 'Cancelada',
            self::NO_SHOW     => 'No asistió',
            default           => 'Desconocido',
        };
    }

    public function specialty()
    {
        return $this->belongsTo(Specialty::class);
    }
}
