<?php

namespace App\Support;

use App\Models\ClinicBranch;
use App\Models\User;
use Illuminate\Support\Collection;

class ClinicBranchContext
{
    public static function isSuperAdministrator(User $user): bool
    {
        return $user->customer_id === null
            && $user->roles()->whereNull('roles.customer_id')
                ->where('roles.name', 'Super Administrador')->exists();
    }

    public static function isHeadquartersActive(int $customerId): bool
    {
        $headquartersId = (int) ClinicBranch::query()->where('customer_id', $customerId)->orderBy('id')->value('id');
        return $headquartersId > 0
            && (int) session('active_branch_customer_id') === $customerId
            && (int) session('active_branch_id') === $headquartersId;
    }

    public static function canOperateAcrossBranches(User $user, int $customerId): bool
    {
        if ($user->customer_id === null) {
            return true;
        }

        $headquartersId = (int) ClinicBranch::query()->where('customer_id', $customerId)->orderBy('id')->value('id');
        return $headquartersId > 0 && $user->branchRoles()
            ->where('roles.customer_id', $customerId)
            ->wherePivot('customer_id', $customerId)
            ->wherePivot('clinic_branch_id', $headquartersId)
            ->whereExists(function ($query) use ($customerId) {
                $query->selectRaw('1')
                    ->from('clinic_branch_user')
                    ->join('clinic_branches', 'clinic_branches.id', '=', 'clinic_branch_user.clinic_branch_id')
                    ->whereColumn('clinic_branch_user.user_id', 'clinic_branch_user_role.user_id')
                    ->whereColumn('clinic_branch_user.clinic_branch_id', 'clinic_branch_user_role.clinic_branch_id')
                    ->where('clinic_branch_user.status', true)
                    ->where('clinic_branches.customer_id', $customerId)
                    ->where('clinic_branches.status', true);
            })
            ->whereHas('permissions', fn ($permissions) => $permissions
                ->where('permissions.slug', 'branches.operate_all'))
            ->exists();
    }

    public static function canAdministerAllBranches(User $user, int $customerId): bool
    {
        if ($user->customer_id === null) {
            return true;
        }

        $headquartersId = (int) ClinicBranch::query()->where('customer_id', $customerId)->orderBy('id')->value('id');
        return $headquartersId > 0
            && self::isHeadquartersActive($customerId)
            && self::hasExplicitPermissionInBranch($user, $customerId, $headquartersId, 'branches.admin_all');
    }

    private static function hasExplicitPermissionInBranch(User $user, int $customerId, int $branchId, string $permission): bool
    {
        return $user->branchRoles()->where('roles.customer_id', $customerId)
            ->wherePivot('customer_id', $customerId)->wherePivot('clinic_branch_id', $branchId)
            ->whereHas('permissions', fn ($permissions) => $permissions->where('permissions.slug', $permission))
            ->exists();
    }

    public static function centralAdministrationBranches(User $user, int $customerId): Collection
    {
        if (!self::canAdministerAllBranches($user, $customerId)) {
            return collect();
        }

        return ClinicBranch::query()->where('customer_id', $customerId)->where('status', true)
            ->orderBy('name')->get();
    }

    public static function hasPermissionInBranch(User $user, int $customerId, int $branchId, string $permission): bool
    {
        // El superadministrador global no depende de permisos asignados a roles de clínica.
        if (self::isSuperAdministrator($user)) {
            return true;
        }

        $roles = $user->clinicRolesForBranch($customerId, $branchId);
        $roleStates = (clone $roles)->get(['roles.permissions_configured']);

        if ($roleStates->isEmpty()) {
            return false;
        }

        if ($roleStates->every(fn ($role) => !(bool) $role->permissions_configured)) {
            return true;
        }

        return $roles->whereHas('permissions', fn ($permissions) => $permissions
            ->where('permissions.slug', $permission))->exists();
    }

    public static function isGeneralAdministrator(User $user, int $customerId): bool
    {
        return $user->customer_id === null || $user->hasClinicRole('Administrador', $customerId);
    }

    public static function availableBranches(User $user, int $customerId): Collection
    {
        $canOperateAcrossBranches = self::canOperateAcrossBranches($user, $customerId);

        return ClinicBranch::query()
            ->where('customer_id', $customerId)
            ->where('status', true)
            ->when($user->customer_id !== null && !$canOperateAcrossBranches, fn ($query) => $query
                ->whereHas('users', fn ($users) => $users
                    ->whereKey($user->id)
                    ->where('clinic_branch_user.status', true)))
            ->orderBy('id')
            ->get();
    }

    public static function defaultBranch(User $user, int $customerId): ?ClinicBranch
    {
        $branches = self::availableBranches($user, $customerId);
        $activeBranchId = (int) session('active_branch_id');
        $activeCustomerId = (int) session('active_branch_customer_id');

        if ($activeCustomerId === $customerId && $activeBranchId) {
            $current = $branches->firstWhere('id', $activeBranchId);
            if ($current) {
                return $current;
            }
        }

        $assignedBranch = $user->branches()
            ->where('clinic_branches.customer_id', $customerId)
            ->where('clinic_branches.status', true)
            ->wherePivot('status', true)
            ->orderBy('clinic_branches.id')
            ->first();

        if ($assignedBranch) {
            return $assignedBranch;
        }

        return $branches->first();
    }
}
