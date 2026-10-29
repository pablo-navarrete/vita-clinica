<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class WebsiteBlogSetting extends Model
{
    protected $fillable = ['customer_id', 'enabled', 'title', 'banner_path'];

    protected $casts = ['enabled' => 'boolean'];

    public function getTitleAttribute($value): ?string
    {
        return $value === null ? null : Str::upper($value);
    }

    public function setTitleAttribute($value): void
    {
        $this->attributes['title'] = $value === null ? null : Str::upper(trim($value));
    }
}
