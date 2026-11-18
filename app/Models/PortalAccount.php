<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;

class PortalAccount extends Authenticatable
{
    protected $fillable = ['name','email','password'];
    protected $hidden = ['password','session_version'];
    protected $casts = ['email_verified_at'=>'datetime','active'=>'boolean'];
}
