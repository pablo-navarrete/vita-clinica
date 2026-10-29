<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ClinicMailSetting extends Model
{
    protected $fillable = [
        'customer_id',
        'enabled',
        'from_email',
        'from_name',
        'host',
        'port',
        'encryption',
        'username',
        'password',
    ];

    protected $casts = [
        'enabled' => 'boolean',
        'password' => 'encrypted',
    ];
}
