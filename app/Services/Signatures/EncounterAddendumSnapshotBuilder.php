<?php
namespace App\Services\Signatures;

use App\Models\{Encounter, EncounterDocumentVersion, User};
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class EncounterAddendumSnapshotBuilder
{
    public const TYPES = ['observation' => 'Observación adicional', 'complement' => 'Complemento', 'rectification' => 'Rectificación'];
    public const FIELDS = ['chief_complaint' => 'Motivo', 'clinical_assessment' => 'Evaluación clínica / diagnóstico', 'treatment_plan' => 'Plan / tratamiento', 'content' => 'Contenido de la adenda'];

    public function build(Encounter $encounter, User $doctor, array $input): array
    {
        $input['content'] = is_string($input['content'] ?? null) ? trim($input['content']) : null;
        $input['reason'] = is_string($input['reason'] ?? null) ? trim($input['reason']) : null;
        $data = Validator::make($input, [
            'type' => ['required', Rule::in(array_keys(self::TYPES))], 'content' => ['required', 'string', 'max:20000'],
            'rectifies_version_id' => ['required_if:type,rectification', 'nullable', 'integer', 'min:1'],
            'target_field' => ['required_if:type,rectification', 'nullable', Rule::in(array_keys(self::FIELDS))],
            'reason' => ['required_if:type,rectification', 'nullable', 'string', 'max:2000'],
        ])->validate();
        $verifier = app(EncounterSignatureVerifier::class);
        $original = $encounter->signature;
        $root = $verifier->verify($original);
        $last = EncounterDocumentVersion::where('customer_id', $encounter->customer_id)->where('encounter_id', $encounter->id)
            ->whereHas('signature')->orderByDesc('version')->firstOrFail();
        if ($last->id !== $original->document_version_id) $verifier->verify($last->signature);
        $correction = null;
        if ($data['type'] === 'rectification') {
            $target = EncounterDocumentVersion::where('customer_id', $encounter->customer_id)->where('encounter_id', $encounter->id)
                ->whereHas('signature')->findOrFail($data['rectifies_version_id']);
            $targetSnapshot = $verifier->verify($target->signature);
            $field = $data['target_field'];
            $allowed = $target->kind === 'original' ? ['chief_complaint', 'clinical_assessment', 'treatment_plan'] : ['content'];
            if (!in_array($field, $allowed, true)) throw \Illuminate\Validation\ValidationException::withMessages(['target_field' => 'Selecciona un apartado del documento elegido.']);
            $correction = ['document_id' => $target->id, 'document_version' => (int) $target->version, 'document_sha256' => $target->sha256,
                'field' => $field, 'previous_text' => $targetSnapshot[$field] ?? null, 'reason' => $data['reason']];
        }
        return [
            'schema_version' => 4, 'kind' => 'addendum', 'customer_id' => (int) $encounter->customer_id,
            'encounter_id' => $encounter->id, 'addendum_type' => $data['type'], 'content' => $data['content'],
            'patient' => $root['patient'], 'branch' => $root['branch'], 'specialty' => $root['specialty'],
            'professional' => app(SignerIdentitySnapshot::class)->capture($doctor),
            'original_document' => ['id' => $original->document_version_id, 'sha256' => $original->content_hash],
            'previous_document' => ['id' => $last->id, 'version' => (int) $last->version, 'sha256' => $last->sha256],
            'sequence' => (int) $last->version + 1, 'correction' => $correction,
            'visual_assets' => app(SignatureVisualAssets::class)->forUser($doctor, (int) $encounter->customer_id),
        ];
    }

    public function inputFromSnapshot(array $snapshot): array
    {
        return ['type' => $snapshot['addendum_type'], 'content' => $snapshot['content'],
            'rectifies_version_id' => $snapshot['correction']['document_id'] ?? null,
            'target_field' => $snapshot['correction']['field'] ?? null, 'reason' => $snapshot['correction']['reason'] ?? null];
    }
}
