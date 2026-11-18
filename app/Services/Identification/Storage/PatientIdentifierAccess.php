<?php

namespace App\Services\Identification\Storage;

use App\Models\Patient;
use App\Models\User;
use App\Support\ClinicBranchContext;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

/** Narrow server-side contexts; never selected from an HTTP parameter. */
final class PatientIdentifierAccess implements IdentifierAuthorizer
{
    public function __construct(private ?Patient $newPatient = null) {}

    public function authorize(User $actor, IdentifierSubject $subject, object $owner, bool $write): void
    {
        $actor = $actor->fresh();
        $branch = (int) session('active_branch_id');
        $customer = (int) app('customer')->id;
        $valid = $actor && (int) $actor->status === 1 && (int) $actor->customer_id === $customer
            && $subject->kind === 'patient' && $subject->customerId === $customer
            && (int) $owner->customer_id === $customer
            && (int) session('active_branch_customer_id') === $customer
            && ClinicBranchContext::availableBranches($actor, $customer)->contains('id', $branch);
        if ($this->newPatient) {
            $valid = $valid && DB::transactionLevel() > 0 && $this->newPatient->wasRecentlyCreated
                && $this->newPatient->id === $subject->ownerId && (int) $owner->clinic_branch_id === $branch
                && ClinicBranchContext::hasPermissionInBranch($actor, $customer, $branch, 'patients.create');
        } else {
            $valid = $valid && ! $write
                && (ClinicBranchContext::hasPermissionInBranch($actor, $customer, $branch, 'medical_records.view')
                    || ClinicBranchContext::hasPermissionInBranch($actor, $customer, $branch, 'medical_records.manage'));
        }
        if (! $valid) throw new AuthorizationException('No tienes autorización para consultar esta identificación clínica.');
    }
}
