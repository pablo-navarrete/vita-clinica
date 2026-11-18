<?php
namespace App\Policies;
use App\Models\User;
use App\Support\ClinicBranchContext;
class BillingPlanPolicy
{
    public function manage(User $user): bool
    {
        // Legacy global accounts have no status; tenant users never qualify here.
        return ($user->status === null || (int) $user->status === 1)
            && ClinicBranchContext::isSuperAdministrator($user);
    }
}
