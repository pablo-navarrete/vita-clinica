<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PreviousSurgerie extends Model
{
    use HasFactory;

     protected $fillable = [
        'medical_record_id',
        'name',
        'customer_id'
    ];

    public function medicalRecord()
    {
        return $this->belongsTo(MedicalRecord::class);
    }
}
