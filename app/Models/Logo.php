<?php

namespace App\Models;

use App\Models\LogoLocation;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Logo extends Model
{
    use HasFactory;
    protected $fillable = [
        'status',
        'url',
        'name',
        'customer_id',
    ];

    public function locations()
    {
        return $this->hasMany(LogoLocation::class)
            ->where('customer_id', app('customer')->id);
    }
    
}
