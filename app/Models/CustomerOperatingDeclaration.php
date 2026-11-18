<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CustomerOperatingDeclaration extends Model
{
    public $timestamps = false;
    protected $guarded = ['id'];
    protected $casts = ['accepted' => 'boolean', 'accepted_at' => 'immutable_datetime'];

    protected static function booted(): void
    {
        static::updating(fn () => throw new \LogicException('Operating evidence is immutable.'));
        static::deleting(fn () => throw new \LogicException('Operating evidence must be retained.'));
    }
}
