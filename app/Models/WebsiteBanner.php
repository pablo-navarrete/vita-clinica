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
        'position',
        'status',
    ];

    protected $casts = [
        'status' => 'boolean',
        'position' => 'integer',
    ];
}
