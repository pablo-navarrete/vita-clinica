<?php

namespace App\Services\Billing;

use App\Models\{ClinicBranch, Customer, CustomerSubscription};
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class BranchCapacityService
{
    // Call after locking the customer, so competing creations/reactivations serialize.
    private function assertAvailable(Customer $customer): void
    {
        $subscription = CustomerSubscription::where('customer_id', $customer->id)->lockForUpdate()->first();
        if (!$subscription) return; // Preserve clients not yet enrolled in the commercial module.

        $active = $customer->branches()->where('status', true)->count();
        if ($active >= (int) $subscription->branch_quantity) {
            throw ValidationException::withMessages([
                'branch_limit' => "Este cliente tiene {$subscription->branch_quantity} sede(s) contratada(s) y {$active} activa(s), incluida la principal. Desactiva una sede o amplía las sedes contratadas antes de crear o activar otra.",
            ]);
        }
    }

    public function create(Customer $customer, array $data): ClinicBranch
    {
        return DB::transaction(function () use ($customer, $data) {
            $customer = Customer::lockForUpdate()->findOrFail($customer->id);
            $this->assertAvailable($customer);
            $branch = $customer->branches()->create(collect($data)->only(['name', 'address', 'phone'])->all() + ['status' => true]);
            $this->assignGeneralAdministrators($customer, $branch);
            return $branch;
        });
    }

    public function setStatus(Customer $customer, int $branchId, bool $active): ClinicBranch
    {
        return DB::transaction(function () use ($customer, $branchId, $active) {
            $customer = Customer::lockForUpdate()->findOrFail($customer->id);
            $branch = $customer->branches()->lockForUpdate()->findOrFail($branchId);
            if ($active && !$branch->status) $this->assertAvailable($customer);
            if (!$active && $branch->status && $customer->branches()->where('status', true)->count() <= 1) {
                throw ValidationException::withMessages(['branch_limit' => 'La clínica debe conservar al menos una sede activa.']);
            }
            $branch->update(['status' => $active]);
            if ($active) $this->assignGeneralAdministrators($customer, $branch);
            return $branch;
        });
    }

    private function assignGeneralAdministrators(Customer $customer, ClinicBranch $branch): void
    {
        $admins = \App\Models\User::where('customer_id', $customer->id)
            ->where('is_customer_administrator', true)->get();
        if ($admins->isEmpty()) return;
        $role = \App\Models\Role::where('customer_id', $customer->id)->where('name', 'Administrador')->firstOrFail();
        foreach ($admins as $admin) {
            $admin->branches()->syncWithoutDetaching([$branch->id => ['status' => 1]]);
            if (!$admin->branchRoles()->wherePivot('customer_id', $customer->id)->wherePivot('clinic_branch_id', $branch->id)->whereKey($role->id)->exists()) {
                $admin->branchRoles()->attach($role->id, ['customer_id' => $customer->id, 'clinic_branch_id' => $branch->id]);
            }
        }
    }
}
