<?php

namespace App\Policies;

use App\Models\{Patient, User};
use App\Support\ClinicBranchContext;

class PortalClinicalAccessPolicy
{
    public function manage(User $user, Patient $patient): bool
    {
        $customer = app()->bound('customer') ? app('customer') : null;
        if (!$customer || (int)$user->customer_id !== (int)$customer->id || (int)$user->status !== 1
            || (int)$patient->customer_id !== (int)$customer->id || (int)$customer->status !== 1) return false;
        $branch=ClinicBranchContext::defaultBranch($user,(int)$customer->id);
        return $branch && $branch->status
            && $user->branches()->whereKey($branch->id)->wherePivot('status',true)->exists()
            && $user->hasClinicRole('Administrador',(int)$customer->id,(int)$branch->id)
            && ClinicBranchContext::canAdministerAllBranches($user,(int)$customer->id)
            && ClinicBranchContext::hasPermissionInBranch($user,(int)$customer->id,(int)$branch->id,'medical_records.manage');
    }
}
