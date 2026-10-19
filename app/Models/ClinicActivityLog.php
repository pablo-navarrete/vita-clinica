<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ClinicActivityLog extends Model
{
    protected $fillable = [
        'customer_id', 'user_id', 'actor_name', 'module', 'action', 'subject_type',
        'subject_id', 'subject_label', 'summary', 'changes',
    ];

    protected $casts = ['changes' => 'array'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
