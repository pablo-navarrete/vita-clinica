<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WebsitePageSection extends Model
{
    protected $fillable = [
        'website_page_id',
        'title',
        'body',
        'image_path',
        'image_alt',
        'image_position',
        'position',
    ];

    protected $casts = [
        'position' => 'integer',
    ];

    public function page(): BelongsTo
    {
        return $this->belongsTo(WebsitePage::class, 'website_page_id');
    }
}
