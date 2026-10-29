<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'status',
        'customer_id',
        'rut',
        'phone'
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
    ];

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function roles()
    {
        if ($this->customer_id && app()->bound('customer') && (int) app('customer')->id === (int) $this->customer_id) {
            $customerId = (int) $this->customer_id;
            $activeCustomerId = (int) session('active_branch_customer_id');
            $branchId = $activeCustomerId === $customerId ? (int) session('active_branch_id') : 0;
            if (!$branchId) {
                $branchId = (int) $this->branches()->orderBy('clinic_branches.id')->value('clinic_branches.id');
            }

            return $this->clinicRolesForBranch($customerId, $branchId);
        }

        return $this->belongsToMany(Role::class);
    }

    public function branchRoles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'clinic_branch_user_role')
            ->withPivot(['customer_id', 'clinic_branch_id'])
            ->withTimestamps();
    }

    public function clinicRolesForBranch(int $customerId, ?int $branchId = null): BelongsToMany
    {
        if ($branchId === null) {
            $branchId = (int) session('active_branch_customer_id') === $customerId
                ? (int) session('active_branch_id') : 0;
        }
        if (!$branchId) {
            $branchId = (int) $this->branches()->where('clinic_branches.customer_id', $customerId)
                ->orderBy('clinic_branches.id')->value('clinic_branches.id');
        }

        return $this->branchRoles()
            ->wherePivot('customer_id', $customerId)
            ->wherePivot('clinic_branch_id', $branchId)
            ->where('roles.customer_id', $customerId)
            ->whereExists(function ($query) use ($customerId, $branchId) {
                $query->selectRaw('1')->from('clinic_branch_user')
                    ->whereColumn('clinic_branch_user.user_id', 'clinic_branch_user_role.user_id')
                    ->whereColumn('clinic_branch_user.clinic_branch_id', 'clinic_branch_user_role.clinic_branch_id')
                    ->where('clinic_branch_user.clinic_branch_id', $branchId)
                    ->where('clinic_branch_user_role.customer_id', $customerId);
            });
    }

    public function hasClinicRole(string $name, int $customerId, ?int $branchId = null): bool
    {
        return $this->clinicRolesForBranch($customerId, $branchId)
            ->whereRaw('LOWER(TRIM(roles.name)) = ?', [mb_strtolower(trim($name))])
            ->exists();
    }

    public function syncClinicRoleMirror(int $customerId): void
    {
        $roleIds = \Illuminate\Support\Facades\DB::table('clinic_branch_user_role')
            ->where('customer_id', $customerId)->where('user_id', $this->id)
            ->distinct()->pluck('role_id')->map(fn ($id) => (int) $id)->all();

        \Illuminate\Support\Facades\DB::table('role_user')->where('user_id', $this->id)
            ->whereIn('role_id', \Illuminate\Support\Facades\DB::table('roles')->where('customer_id', $customerId)->select('id'))
            ->delete();

        if ($roleIds) {
            $now = now();
            \Illuminate\Support\Facades\DB::table('role_user')->insert(array_map(fn ($roleId) => [
                'user_id' => $this->id,
                'role_id' => $roleId,
                'created_at' => $now,
                'updated_at' => $now,
            ], $roleIds));
        }
    }

    public function branches(): BelongsToMany
    {
        return $this->belongsToMany(ClinicBranch::class, 'clinic_branch_user')
            ->withPivot('status')
            ->withTimestamps();
    }
     public function specialties(): BelongsToMany
    {
        return $this->belongsToMany(Specialty::class)
            ->withPivot(['customer_id', 'clinic_branch_id'])
            ->withTimestamps();
    }

    public function specialtiesForBranch(int $customerId, int $branchId): BelongsToMany
    {
        return $this->specialties()
            ->wherePivot('customer_id', $customerId)
            ->wherePivot('clinic_branch_id', $branchId)
            ->where('specialties.customer_id', $customerId);
    }

    public function professionalSchedules(): HasMany
    {
        return $this->hasMany(ProfessionalSchedule::class);
    }

    public function appointments()
    {
        return $this->hasMany(Appointment::class, 'professional_id');
    }
}
