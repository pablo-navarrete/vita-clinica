<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WebsiteFooter extends Model
{
    protected $fillable = [
        'customer_id', 'enabled', 'show_social_links', 'title', 'description', 'phone', 'email', 'address',
    ];

    protected $casts = [
        'enabled' => 'boolean',
        'show_social_links' => 'boolean',
    ];
}
