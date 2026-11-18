<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WebsiteBanner extends Model
{
    protected $fillable = [
        'customer_id',
        'title',
        'subtitle',
        'description',
        'button_text',
        'button_url',
        'image_path',
        'image_alt',
        'dark_overlay',
        'image_only',
        'text_alignment',
        'button_alignment',
        'position',
        'status',
    ];

    protected $casts = [
        'status' => 'boolean',
        'dark_overlay' => 'boolean',
        'image_only' => 'boolean',
        'position' => 'integer',
    ];
}
