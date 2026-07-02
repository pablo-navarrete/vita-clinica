<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Customer extends Model
{
    use HasFactory;
    use SoftDeletes;
    protected $fillable = [
        'name',
        'legal_name',
        'status',
        'domain',
        'address',
        'holder',
        'rut',
        'phone',
        'email',
        'payment_status',
        'paid_at',
        'expires_at',
        'color'
    ];
}
