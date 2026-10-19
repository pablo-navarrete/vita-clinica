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


    public function specialty()
    {
        return $this->belongsTo(Specialty::class);
    }

    public function encounter()
    {
        return $this->hasOne(Encounter::class)->latestOfMany();
    }
}
