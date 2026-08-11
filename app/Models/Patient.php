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
        'insurance_id',
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


    public function appointments()
    {
        return $this->hasMany(Appointment::class);
    }

    public function medicalRecord()
    {
        return $this->hasOne(MedicalRecord::class);
    }
    
    public function insurance()
    {
        return $this->belongsTo(Insurance::class);
    }

    public function encounters()
    {
        return $this->hasMany(Encounter::class);
    }

 


}
