<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Patient extends Model
{
    use HasFactory;
       protected $fillable = [
        'customer_id',
        'name',
        'rut',
        'birth_date',
        'gender',
        'phone',
        'email',
        'address',
        'status',
    ];

    protected $casts = [
        'birth_date' => 'date',
    ];

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function insurances()
    {
        return $this->hasMany(PatientInsurance::class);
    }

    public function activeInsurance()
    {
        return $this->hasOne(PatientInsurance::class)
            ->where('is_active', true);
    }

    public function appointments()
    {
        return $this->hasMany(Appointment::class);
    }

    public function medicalRecord()
    {
        return $this->hasOne(MedicalRecord::class);
    }

    /*
    |--------------------------------------------------------------------------
    | Accessors
    |--------------------------------------------------------------------------
    */

    public function getGenderLabelAttribute()
    {
        return match ($this->gender) {
            'male' => 'Masculino',
            'female' => 'Femenino',
            'other' => 'Otro',
            default => '-',
        };
    }

    public function getStatusLabelAttribute()
    {
        return $this->status ? 'Activo' : 'Inactivo';
    }
}
