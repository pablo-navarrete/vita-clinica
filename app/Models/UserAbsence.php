<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserAbsence extends Model
{
    protected $fillable = [
        'customer_id', 'user_id', 'created_by', 'type', 'starts_on', 'ends_on', 'notes',
    ];

    protected $casts = [
        'starts_on' => 'date',
        'ends_on' => 'date',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
