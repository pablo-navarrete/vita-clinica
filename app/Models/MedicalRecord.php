<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MedicalRecord extends Model
{
    use HasFactory;

    protected $fillable = [
        'patient_id',
        'customer_id',
    ];

    public function patient()
    {
        return $this->belongsTo(Patient::class);
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function allergies()
    {
        return $this->hasMany(Allergie::class);
    }

    public function chronicDiseases ()
    {
        return $this->hasMany(ChronicDiseases ::class);
    }

    public function regularMedications ()
    {
        return $this->hasMany(RegularMedication ::class);
    }

    public function previousSurgeries ()
    {
        return $this->hasMany(PreviousSurgerie ::class);
    }
}

