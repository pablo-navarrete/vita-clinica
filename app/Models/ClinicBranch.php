<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ClinicBranch extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'customer_id',
        'name',
        'address',
        'phone',
        'status',
    ];

    protected $casts = [
        'status' => 'boolean',
    ];

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function appointments()
    {
        return $this->hasMany(Appointment::class);
    }

    public function users()
    {
        return $this->belongsToMany(User::class, 'clinic_branch_user')->withPivot('status')->withTimestamps();
    }

    public function patients()
    {
        return $this->hasMany(Patient::class);
    }
}
