<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WebsiteMaintenance extends Model
{
    protected $fillable = ['customer_id', 'enabled', 'message'];

    protected $casts = ['enabled' => 'boolean'];
}
