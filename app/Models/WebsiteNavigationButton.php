<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WebsiteNavigationButton extends Model
{
    protected $fillable = [
        'customer_id',
        'enabled',
        'public_booking_enabled',
        'label',
        'destination',
        'menu_id',
        'whatsapp_phone',
        'custom_url',
    ];

    protected $casts = [
        'enabled' => 'boolean',
        'public_booking_enabled' => 'boolean',
    ];

    public function menu(): BelongsTo
    {
        return $this->belongsTo(Menu::class);
    }
}
