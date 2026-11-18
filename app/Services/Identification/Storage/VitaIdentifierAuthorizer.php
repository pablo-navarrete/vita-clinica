<?php

namespace App\Services\Identification\Storage;

use App\Models\User;
use App\Support\ClinicBranchContext;
use Illuminate\Auth\Access\AuthorizationException;

final class VitaIdentifierAuthorizer implements IdentifierAuthorizer
{
    public function authorize(User $actor, IdentifierSubject $subject, object $owner, bool $write): void
    {
        $actor = $actor->fresh();
        if (! $actor || (int) $actor->status !== 1) {
            $this->deny();
        }
        if (ClinicBranchContext::isSuperAdministrator($actor)) {
            // Global customer/user administration does not grant access to clinical patient identities.
            if ($subject->kind === 'patient') {
                $this->deny();
            }

            return;
        }
        if ($subject->kind === 'customer' || $subject->customerId === null
            || (int) $actor->customer_id !== $subject->customerId) {
            $this->deny();
        }
        $branchId = (int) session('active_branch_id');
        if ((int) session('active_branch_customer_id') !== $subject->customerId || ! $branchId
            || ! ClinicBranchContext::availableBranches($actor, $subject->customerId)->contains('id', $branchId)) {
            $this->deny();
        }
        if ($subject->kind === 'user' && ! $write && (int) $actor->id === $subject->ownerId) {
            return;
        }
        // Profile's existing rule: active-branch administrators may edit their own identity.
        if ($subject->kind === 'user' && $write && (int) $actor->id === $subject->ownerId
            && ($actor->hasClinicRole('Administrador', $subject->customerId, $branchId)
                || $actor->hasClinicRole('Administrador de sede', $subject->customerId, $branchId))) {
            return;
        }
        $permission = $subject->kind === 'patient' ? ($write ? 'patients.update' : 'patients.view') : 'professionals.manage';
        if (! ClinicBranchContext::hasPermissionInBranch($actor, $subject->customerId, $branchId, $permission)) {
            $this->deny();
        }
        if ($subject->kind === 'patient' && ! empty($owner->clinic_branch_id)
            && (int) $owner->clinic_branch_id !== $branchId
            && ! ClinicBranchContext::canAdministerAllBranches($actor, $subject->customerId)) {
            $this->deny();
        }
        if ($subject->kind === 'user') {
            if ($write && (int) $actor->id === $subject->ownerId
                && ! $actor->hasClinicRole('Administrador', $subject->customerId, $branchId)
                && ! $actor->hasClinicRole('Administrador de sede', $subject->customerId, $branchId)) {
                $this->deny();
            }
            if (! User::whereKey($subject->ownerId)->where('customer_id', $subject->customerId)
                ->whereHas('branches', fn ($q) => $q->where('clinic_branches.id', $branchId)->where('clinic_branch_user.status', true))->exists()
                && ! ClinicBranchContext::canAdministerAllBranches($actor, $subject->customerId)) {
                $this->deny();
            }
        }
    }

    private function deny(): never
    {
        throw new AuthorizationException('No tienes autorización para acceder a estos identificadores.');
    }
}
