<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CustomerOperatingReview extends Model
{
    public $timestamps = false;
    protected $guarded = ['id'];
    protected $casts = ['reviewed_at' => 'immutable_datetime'];

    protected static function booted(): void
    {
        static::updating(fn () => throw new \LogicException('Operating review is immutable.'));
        static::deleting(fn () => throw new \LogicException('Operating review must be retained.'));
    }
}
