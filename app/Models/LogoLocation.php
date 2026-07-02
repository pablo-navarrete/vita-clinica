<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LogoLocation extends Model
{
    use HasFactory;
        protected $fillable = [
        'location',
        'logo_id',
        'customer_id',
    ];

    public function logo(): BelongsTo
    {
        return $this->belongsTo(Logo::class);
    }
     public function scopeByCustomer($query, $customerId)
    {
        return $query->where('customer_id', $customerId);
    }
}
