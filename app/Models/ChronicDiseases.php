<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ChronicDiseases extends Model
{
    use HasFactory;
     protected $fillable = [
        'name',
        'medical_record_id',
         'customer_id'
      ];

    public function medicalRecord()
    {
        return $this->belongsTo(MedicalRecord::class);
    }
}
