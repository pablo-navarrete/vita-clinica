<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class Menu extends Model
{
    protected $fillable = [
        'customer_id',
        'website_page_id',
        'name',
        'url',
        'position',
        'status',
    ];

    protected $casts = [
        'status' => 'boolean',
        'position' => 'integer',
    ];

    public function setNameAttribute(string $value): void
    {
        $this->attributes['name'] = Str::upper($value);
    }

    public function getNameAttribute(string $value): string
    {
        return Str::upper($value);
    }

    public function websitePage(): BelongsTo
    {
        return $this->belongsTo(WebsitePage::class);
    }
}
