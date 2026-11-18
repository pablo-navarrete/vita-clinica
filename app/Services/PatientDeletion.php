<?php

namespace App\Services;

use App\Models\Patient;
use App\Models\User;
use App\Support\ClinicBranchContext;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

final class PatientDeletion
{
    public const HISTORY_MESSAGE = 'No se puede eliminar este paciente porque tiene historial o registros asociados que deben conservarse.';

    public function delete(User $actor, int $id): void
    {
        $customer = (int) app('customer')->id;
        $branch = (int) session('active_branch_id');
        abort_unless((int) $actor->customer_id === $customer
            && (int) session('active_branch_customer_id') === $customer
            && ClinicBranchContext::availableBranches($actor, $customer)->contains('id', $branch)
            && ClinicBranchContext::hasPermissionInBranch($actor, $customer, $branch, 'patients.delete'), 403);

        try {
            DB::transaction(function () use ($actor, $id, $customer, $branch) {
                DB::table('customers')->where('id', $customer)->lockForUpdate()->first();
                $patient = Patient::where('customer_id', $customer)->lockForUpdate()->findOrFail($id);
                abort_unless(! $patient->clinic_branch_id || (int) $patient->clinic_branch_id === $branch
                    || ClinicBranchContext::canAdministerAllBranches($actor, $customer), 403);
                $this->assertNoHistory('patients', $id);
                foreach ($patient->medicalRecord()->lockForUpdate()->get() as $record) {
                    $this->assertNoHistory('medical_records', (int) $record->id);
                }
                // Existing model observer retains the deletion event in clinic activity history.
                $patient->delete();
            }, 3);
        } catch (QueryException $error) {
            // A concurrent dependent insert must win over deletion; never cascade around it.
            if (($error->errorInfo[0] ?? null) === '23000') abort(409, self::HISTORY_MESSAGE);
            throw $error;
        }
    }

    private function assertNoHistory(string $parent, int $id): void
    {
        // Inspect all actual FKs, including dependencies introduced by later modules.
        $references = DB::select('SELECT TABLE_NAME,COLUMN_NAME FROM information_schema.KEY_COLUMN_USAGE
            WHERE REFERENCED_TABLE_SCHEMA=DATABASE() AND TABLE_SCHEMA=DATABASE()
            AND REFERENCED_TABLE_NAME=? AND REFERENCED_COLUMN_NAME=?', [$parent, 'id']);
        foreach ($references as $reference) {
            if ($parent === 'patients' && $reference->TABLE_NAME === 'medical_records') continue;
            $query = DB::table($reference->TABLE_NAME)->where($reference->COLUMN_NAME, $id);
            if ($parent === 'patients' && $reference->TABLE_NAME === 'patient_audit_logs') {
                // Only the automatic initial registration events may accompany an otherwise empty record.
                $query->where(fn ($audit) => $audit->where('action', '<>', 'created')->orWhereNull('action'));
            }
            // Current locking reads also see dependencies committed after this transaction began.
            abort_if($query->lockForUpdate()->first() !== null, 409, self::HISTORY_MESSAGE);
        }
    }
}
