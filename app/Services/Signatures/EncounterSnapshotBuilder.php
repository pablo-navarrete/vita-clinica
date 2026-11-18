<?php
namespace App\Services\Signatures;

use App\Models\Encounter;
use App\Models\User;

class EncounterSnapshotBuilder
{
    public function build(Encounter $encounter, User $doctor, array $data): array
    {
        $encounter->load(['patient', 'clinicBranch', 'specialty', 'documents' => fn ($q) => $q->orderBy('id')]);
        $specialty = $encounter->specialty ?? $encounter->appointment?->specialty;
        abort_if($specialty && (int) $specialty->customer_id !== (int) $encounter->customer_id, 409, 'La especialidad no corresponde a la clínica.');
        foreach ($encounter->documents as $document) {
            abort_unless((int) $document->customer_id === (int) $encounter->customer_id
                && (int) $document->patient_id === (int) $encounter->patient_id
                && (int) $document->clinic_branch_id === (int) $encounter->clinic_branch_id,
                409, 'Un adjunto no corresponde a la clínica, paciente o sede de la atención.');
        }
        return [
            'schema_version' => 4, 'scope' => 'encounter_only',
            'customer_id' => (int) $encounter->customer_id, 'encounter_id' => $encounter->id,
            'patient' => app(\App\Services\Identification\PatientIdentification::class)->snapshot($encounter->patient, $doctor),
            'professional' => app(SignerIdentitySnapshot::class)->capture($doctor),
            'branch' => ['id' => $encounter->clinic_branch_id, 'name' => $encounter->clinicBranch?->name],
            'specialty' => ['id' => $specialty?->id, 'name' => $specialty?->name],
            'appointment_id' => $encounter->appointment_id,
            'modality' => $encounter->modality, 'started_at' => $encounter->started_at?->utc()->toIso8601String(),
            'chief_complaint' => $encounter->chief_complaint,
            'clinical_assessment' => trim($data['clinical_assessment']),
            'treatment_plan' => filled($data['treatment_plan'] ?? null) ? trim($data['treatment_plan']) : null,
            'attachments' => $encounter->documents->map(fn ($doc) => [
                'id' => $doc->id, 'name' => $doc->original_name, 'description' => $doc->description,
                'sha256' => $doc->sha256, 'bytes' => (int) $doc->size,
            ])->all(),
            'visual_assets' => app(SignatureVisualAssets::class)->forUser($doctor, (int) $encounter->customer_id),
        ];
    }
}
