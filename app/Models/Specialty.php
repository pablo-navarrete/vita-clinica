<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Specialty extends Model
{
    use HasFactory;

    protected $fillable = [
        'customer_id',
        'name',
        'status',
    ];

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }


    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class)
            ->withPivot('customer_id')
            ->withTimestamps();
    }
}
