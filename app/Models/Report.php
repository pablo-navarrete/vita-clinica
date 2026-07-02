<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Report extends Model
{
    use HasFactory;

     protected $fillable = [
        'name',
        'professional_id',
        'assistant_id',
        'customer_id',
        'price',
        'status', //pagado o pendiente
        'state', //realizado
        'file'
    ];
}
