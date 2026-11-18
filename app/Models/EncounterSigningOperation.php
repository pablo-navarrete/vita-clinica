<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EncounterSigningOperation extends Model
{
    public $incrementing = false;
    protected $keyType = 'string';
    protected $guarded = [];
    protected $hidden = ['payload'];
    protected $casts = ['payload' => 'encrypted', 'expires_at' => 'datetime'];
}
