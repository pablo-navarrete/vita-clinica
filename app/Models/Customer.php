<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Customer extends Model
{
    use HasFactory;
    use SoftDeletes;
    protected $fillable = [
        'name',
        'legal_name',
        'status',
        'domain',
        'address',
        'holder',
        'rut',
        'phone',
        'email',
        'payment_status',
        'paid_at',
        'expires_at',
        'color',
        'operating_country_code'
    ];

    protected static function booted(): void
    {
        static::saving(function (self $customer) {
            $country = $customer->getAttribute('operating_country_code') ?? 'CL';
            if ($country !== 'CL') throw new \InvalidArgumentException('VITA solo permite operar en Chile.');
            $customer->setAttribute('operating_country_code', 'CL');
        });
    }

    public function operatingDeclaration() { return $this->hasOne(CustomerOperatingDeclaration::class)->latestOfMany(); }
    public function operatingReview() { return $this->hasOne(CustomerOperatingReview::class)->latestOfMany(); }

    public function branches()
    {
        return $this->hasMany(ClinicBranch::class);
    }

    public function administratorUsers()
    {
        return $this->hasMany(User::class)->whereHas('branchRoles', function ($query) {
            $query->where('roles.name', 'Administrador')
                ->whereColumn('roles.customer_id', 'users.customer_id')
                ->whereColumn('clinic_branch_user_role.customer_id', 'users.customer_id')
                ->whereExists(function ($branch) {
                    $branch->selectRaw('1')->from('clinic_branches')
                        ->whereColumn('clinic_branches.id', 'clinic_branch_user_role.clinic_branch_id')
                        ->whereColumn('clinic_branches.customer_id', 'users.customer_id')->whereNull('clinic_branches.deleted_at');
                });
        })->orderBy('users.id');
    }
    public function directoryProfile(){return $this->hasOne(CustomerDirectoryProfile::class);}
    public function directoryLogos(){
        return $this->hasMany(Logo::class)->where('status',1)->whereExists(function($q){
            $q->selectRaw('1')->from('logo_locations')->whereColumn('logo_locations.logo_id','logos.id')
                ->whereColumn('logo_locations.customer_id','logos.customer_id')->whereIn('logo_locations.location',['header','footer']);
        })->orderBy('id');
    }
    public function publicHome(){return $this->hasOne(WebsitePage::class)->where('slug','inicio')->where('status',true);}
    public function websiteMaintenance(){return $this->hasOne(WebsiteMaintenance::class);}
}
