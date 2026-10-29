<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class Appointment extends Model
{
    use HasFactory;

    protected static function booted(): void
    {
        static::addGlobalScope('active_clinic_branch', function (Builder $query) {
            $user = auth()->user();
            if (!$user || !$user->customer_id) {
                return;
            }

            $customerId = (int) session('active_branch_customer_id');
            $branchId = (int) session('active_branch_id');

            if (!$customerId || !$branchId || (int) $user->customer_id !== $customerId) {
                return;
            }

            $isGeneralAdministrator = $user->roles()
                ->where('roles.customer_id', $customerId)
                ->whereRaw('LOWER(TRIM(roles.name)) = ?', ['administrador'])
                ->exists();

            if (!$isGeneralAdministrator) {
                $query->where($query->getModel()->qualifyColumn('clinic_branch_id'), $branchId);
            }
        });
    }

    protected $casts = [
        'start_at' => 'datetime',
        'end_at' => 'datetime',
        'whatsapp_consent_at' => 'datetime',
    ];

       protected $fillable = [
        'customer_id',
        'clinic_branch_id',
        'patient_id',
        'professional_id',
        'specialty_id',
        'start_at',
        'end_at',
        'status',
        'modality',
        'whatsapp_consent_at',
        'reason',
        'notes',
    ];





    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function clinicBranch()
    {
        return $this->belongsTo(ClinicBranch::class)->withTrashed();
    }

    public function patient()
    {
        return $this->belongsTo(Patient::class);
    }

    public function professional()
    {
        return $this->belongsTo(User::class, 'professional_id');
    }


    public function specialty()
    {
        return $this->belongsTo(Specialty::class);
    }

    public function encounter()
    {
        return $this->hasOne(Encounter::class)->latestOfMany();
    }

    public function telemedicineSession()
    {
        return $this->hasOne(TelemedicineSession::class);
    }
}
