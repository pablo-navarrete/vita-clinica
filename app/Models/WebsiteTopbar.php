<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WebsiteTopbar extends Model
{
    protected $fillable = ['customer_id', 'enabled', 'show_social_links', 'phone', 'email'];

    protected $casts = [
        'enabled' => 'boolean',
        'show_social_links' => 'boolean',
    ];
}
