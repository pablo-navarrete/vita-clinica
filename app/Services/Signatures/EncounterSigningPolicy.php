<?php
namespace App\Services\Signatures;

use App\Models\Encounter;
use App\Models\User;
use App\Support\ClinicBranchContext;

class EncounterSigningPolicy
{
    public function authorize(User $user, Encounter $encounter): void
    {
        $customerId = (int) app('customer')->id;
        $branchId = (int) $encounter->clinic_branch_id;
        $responsible = $encounter->professional_id ?? $encounter->appointment?->professional_id;
        abort_unless((int) $user->customer_id === $customerId && (int) $user->status === 1
            && (int) $encounter->customer_id === $customerId && (int) $responsible === (int) $user->id
            && (int) $encounter->patient?->customer_id === $customerId
            && $user->hasClinicRole('Doctor', $customerId, $branchId)
            && $user->branches()->where('clinic_branches.customer_id', $customerId)->whereKey($branchId)
                ->where('clinic_branches.status', 1)->wherePivot('status', 1)->exists()
            && (int) ClinicBranchContext::defaultBranch($user, $customerId)?->id === $branchId,
            403, 'Solo el médico responsable, desde la sede de la atención, puede firmarla.');
    }
}
