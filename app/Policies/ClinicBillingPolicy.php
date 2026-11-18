<?php

namespace App\Policies;

use App\Models\User;
use App\Support\ClinicBranchContext;

class ClinicBillingPolicy
{
    public function manage(User $user, int $customerId): bool
    {
        if ((int) $user->customer_id !== $customerId || (int) $user->status !== 1) {
            return false;
        }
        $branch = ClinicBranchContext::defaultBranch($user, $customerId);

        return $branch && $branch->status && $user->branches()->whereKey($branch->id)->wherePivot('status', true)->exists()
            && $user->hasClinicRole('Administrador', $customerId, (int) $branch->id);
    }
}
