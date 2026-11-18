<?php

namespace App\Support;

use App\Models\Patient;
use App\Models\PatientAuditLog;
use App\Models\MedicalRecord;
use App\Models\ClinicBranch;
use App\Support\ClinicActivityLogger;

class PatientAuditLogger
{
    public static function recordExport(Patient $patient, string $format): void
    {
        PatientAuditLog::create([
            'customer_id' => $patient->customer_id,
            'patient_id' => $patient->id,
            'clinic_branch_id' => self::branchId($patient),
            'user_id' => auth()->id(),
            'action' => 'exported',
            'section' => 'Exportación de ficha (' . $format . ')',
            'changes' => [],
        ]);
    }

    public static function recordMedicalRecord(
        MedicalRecord $medicalRecord,
        string $section,
        array $before,
        array $after
    ): void {
        $patient = $medicalRecord->patient;

        if ($patient) {
            self::record($patient, 'updated', $section, ['items' => $before], ['items' => $after]);
            ClinicActivityLogger::recordManual(
                (int) $medicalRecord->customer_id,
                'Ficha clínica',
                'updated',
                'ficha clínica',
                $patient->name,
                'Actualizó ' . mb_strtolower($section) . ' de la ficha clínica de «' . $patient->name . '».',
                [$section],
                Patient::class,
                $patient->id
            );
        }
    }

    public static function record(
        Patient $patient,
        string $action,
        string $section,
        array $before = [],
        array $after = [],
        bool $anonymous = false
    ): void {
        $changes = [];

        foreach (array_unique(array_merge(array_keys($before), array_keys($after))) as $field) {
            $oldValue = $before[$field] ?? null;
            $newValue = $after[$field] ?? null;

            if ($oldValue !== $newValue) {
                $changes[$field] = [
                    'before' => $oldValue,
                    'after' => $newValue,
                ];
            }
        }

        if ($action !== 'created' && $changes === []) {
            return;
        }

        PatientAuditLog::create([
            'customer_id' => $patient->customer_id,
            'patient_id' => $patient->id,
            'clinic_branch_id' => self::branchId($patient),
            'user_id' => $anonymous ? null : auth()->id(),
            'action' => $action,
            'section' => $section,
            'changes' => $changes,
        ]);
    }

    private static function branchId(Patient $patient): ?int
    {
        $customerId = (int) $patient->customer_id;
        $activeBranchId = (int) session('active_branch_customer_id') === $customerId
            ? session('active_branch_id')
            : null;
        $branchId = $activeBranchId ?: $patient->clinic_branch_id;

        if (!$branchId) {
            return null;
        }

        $validBranchId = ClinicBranch::withTrashed()
            ->where('customer_id', $customerId)
            ->whereKey((int) $branchId)
            ->value('id');

        return $validBranchId === null ? null : (int) $validBranchId;
    }
}
