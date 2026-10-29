<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WebsiteBlogPost extends Model
{
    protected $fillable = [
        'customer_id', 'user_id', 'title', 'slug', 'excerpt', 'content',
        'image_path', 'status', 'published_at',
    ];

    protected $casts = [
        'status' => 'boolean',
        'published_at' => 'datetime',
    ];
}
