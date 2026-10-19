<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WebsiteSocialSetting extends Model
{
    protected $fillable = ['customer_id', 'social_links', 'show_in_topbar'];

    protected $casts = [
        'social_links' => 'array',
        'show_in_topbar' => 'boolean',
    ];

    public static function networks(): array
    {
        return [
            'facebook' => ['label' => 'Facebook', 'icon' => 'fa-brands fa-facebook-f'],
            'instagram' => ['label' => 'Instagram', 'icon' => 'fa-brands fa-instagram'],
            'linkedin' => ['label' => 'LinkedIn', 'icon' => 'fa-brands fa-linkedin-in'],
            'x' => ['label' => 'X', 'icon' => 'fa-brands fa-x-twitter'],
            'youtube' => ['label' => 'YouTube', 'icon' => 'fa-brands fa-youtube'],
            'whatsapp' => ['label' => 'WhatsApp', 'icon' => 'fa-brands fa-whatsapp'],
            'tiktok' => ['label' => 'TikTok', 'icon' => 'fa-brands fa-tiktok'],
        ];
    }
}
