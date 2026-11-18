<?php
namespace App\Services\Signatures;

use App\Models\{Encounter, EncounterSignature, EncounterSigningOperation, User};
use App\Support\PatientAuditLogger;
use Illuminate\Support\Facades\DB;

class EncounterAddendumService
{
    public const ACCEPTANCE_VERSION = 'vita-fes-addendum-v1';
    public const ACCEPTANCE = 'He revisado esta adenda, sus referencias y, cuando corresponde, el texto anterior y el motivo de rectificación. Acepto firmarla mediante Firma Electrónica Simple (VITA), conservando intactos los documentos anteriores.';

    public function __construct(private EncounterAddendumPolicy $policy, private EncounterAddendumSnapshotBuilder $builder,
        private CanonicalContentSerializer $serializer, private SigningOperationManager $operations,
        private SigningIdentityVerifier $identity, private SignedAttachmentStore $files, private SignedDocumentWriter $writer) {}

    public function prepare(User $user, int $patientId, int $encounterId, array $input): EncounterSigningOperation
    {
        return DB::transaction(function () use ($user, $patientId, $encounterId, $input) {
            app(SignerIdentitySnapshot::class)->lockTenant($user);
            $encounter = $this->encounter($patientId, $encounterId);
            $user = $user->fresh();
            $this->policy->authorize($user, $encounter);
            $snapshot = $this->builder->build($encounter, $user, $input);
            return $this->operations->prepare($user, $encounter, $this->serializer->serialize($snapshot), 'addendum', $encounter->signature->document_version_id);
        });
    }

    public function sign(User $user, int $patientId, int $encounterId, string $operationId, string $password, bool $accepted): EncounterSignature
    {
        $this->operations->requireAcceptance($accepted);
        return DB::transaction(function () use ($user, $patientId, $encounterId, $operationId, $password) {
            app(SignerIdentitySnapshot::class)->lockTenant($user);
            $op = $this->operations->lock($user, $encounterId, $operationId, 'addendum');
            $encounter = $this->encounter($patientId, $encounterId);
            $user = User::whereKey($user->id)->lockForUpdate()->firstOrFail();
            $this->policy->authorize($user, $encounter);
            $this->identity->verify($user, $password);
            $prepared = json_decode($op->payload, true, 512, JSON_THROW_ON_ERROR);
            $snapshot = $this->builder->build($encounter, $user, $this->builder->inputFromSnapshot($prepared));
            abort_unless((int) $op->parent_version_id === (int) $snapshot['original_document']['id'], 409);
            $payload = $this->serializer->serialize($snapshot);
            $this->operations->assertContent($op, $payload);
            $manifest = $this->files->prepareVisuals($op, $snapshot);
            $signature = $this->writer->persist($op, $encounter, $user, $snapshot, $payload, $manifest,
                'addendum', $snapshot['sequence'], self::ACCEPTANCE_VERSION, self::ACCEPTANCE,
                $op->parent_version_id, $snapshot['correction']['document_id'] ?? null);
            PatientAuditLogger::record($encounter->patient, 'updated', 'Adenda médica firmada FES', [], [
                'atención' => '#' . $encounter->id, 'firma de adenda' => '#' . $signature->id,
                'tipo' => EncounterAddendumSnapshotBuilder::TYPES[$snapshot['addendum_type']],
                'documento rectificado' => $snapshot['correction'] ? '#' . $snapshot['correction']['document_id'] : 'No corresponde',
                'SHA-256' => $signature->content_hash,
            ]);
            return $signature;
        });
    }

    private function encounter(int $patientId, int $encounterId): Encounter
    {
        return Encounter::where('customer_id', app('customer')->id)->where('patient_id', $patientId)->lockForUpdate()->findOrFail($encounterId);
    }
}
