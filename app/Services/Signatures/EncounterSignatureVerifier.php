<?php
namespace App\Services\Signatures;

use App\Models\EncounterSignature;

class EncounterSignatureVerifier
{
    public function verify(EncounterSignature $signature, bool $verifyFiles = true): array
    {
        return $this->verifyForCustomer($signature, (int) app('customer')->id, $verifyFiles);
    }

    /** Caller must authorize the document owner; does not resolve or change tenant context. */
    public function verifyForCustomer(EncounterSignature $signature, int $customerId, bool $verifyFiles = true): array
    {
        $verified = [];
        return $this->verifyOne($signature, $verifyFiles, $verified, $customerId);
    }

    public function verifyMany(iterable $signatures, bool $verifyFiles = false): array
    {
        $verified = []; $result = [];
        foreach ($signatures as $signature) $result[$signature->id] = $this->verifyOne($signature, $verifyFiles, $verified, (int) app('customer')->id);
        return $result;
    }

    private function verifyOne(EncounterSignature $signature, bool $verifyFiles, array &$verified, int $customerId): array
    {
        abort_unless($customerId > 0 && (int) $signature->customer_id === $customerId, 403);
        $version = $signature->documentVersion;
        if (isset($verified[$version?->id])) return $verified[$version->id];
        abort_unless($version && (int) $version->customer_id === (int) $signature->customer_id
            && (int) $version->encounter_id === (int) $signature->encounter_id
            && hash_equals($signature->content_hash, $version->sha256)
            && hash_equals($version->sha256, hash('sha256', $version->payload)), 409, 'La versión firmada no supera la verificación de integridad.');
        $snapshot = json_decode($version->payload, true, 512, JSON_THROW_ON_ERROR);
        $schema = $snapshot['schema_version'] ?? null;
        abort_unless(in_array($schema, [1, 2, 3, 4], true) && (int) $version->schema_version === $schema
            && ($schema !== 1 || $version->kind === 'original')
            && ($schema !== 2 || $version->kind === 'addendum'), 409, 'Versión documental no soportada.');
        if ($schema >= 3) {
            $identity = $snapshot['professional'] ?? [];
            $document = $identity['document'] ?? [];
            $required = ['reference', 'country', 'type', 'namespace', 'normalized', 'display', 'normalization_version', 'validation_status', 'identity_verified'];
            abort_unless(count(array_intersect($required, array_keys($document))) === count($required)
                && ($document['identity_verified'] ?? null) === false
                && app(CanonicalContentSerializer::class)->serialize($signature->identity['document'] ?? []) === app(CanonicalContentSerializer::class)->serialize($document)
                && ($signature->identity['name'] ?? null) === ($identity['name'] ?? null)
                && (int) ($signature->identity['id'] ?? 0) === (int) $signature->user_id,
                409, 'Identidad internacional inconsistente.');
        }
        if ($schema === 4) {
            abort_unless((int) ($snapshot['patient']['id'] ?? 0) === (int) $version->patient_id, 409, 'Paciente documental inconsistente.');
            if ($version->kind === 'original') {
                $document = $snapshot['patient']['document'] ?? null;
                abort_unless(is_array($document) && array_key_exists('reference', $document)
                    && array_key_exists('normalized', $document) && ($document['identity_verified'] ?? null) === false,
                    409, 'Identificaci?n del paciente inconsistente.');
            }
        }
        abort_unless((int) ($snapshot['professional']['id'] ?? 0) === (int) $signature->user_id
            && (int) ($snapshot['customer_id'] ?? 0) === (int) $signature->customer_id
            && (int) ($snapshot['encounter_id'] ?? 0) === (int) $signature->encounter_id, 409, 'Identidad documental inconsistente.');
        if ($version->kind === 'addendum') {
            $reference = function (int $id) use ($version) {
                return \App\Models\EncounterDocumentVersion::where('customer_id', $version->customer_id)
                    ->where('encounter_id', $version->encounter_id)->where('patient_id', $version->patient_id)
                    ->where('clinic_branch_id', $version->clinic_branch_id)->where('version', '<', $version->version)->with('signature')->findOrFail($id);
            };
            $parent = $reference((int) $version->parent_version_id);
            $previous = $reference((int) ($snapshot['previous_document']['id'] ?? 0));
            abort_unless($parent->kind === 'original' && $parent->signature && $previous->signature
                && (int) $parent->signature->user_id === (int) $signature->user_id
                && (int) ($snapshot['original_document']['id'] ?? 0) === $parent->id
                && hash_equals($parent->sha256, $snapshot['original_document']['sha256'] ?? '')
                && (int) $previous->version + 1 === (int) $version->version
                && (int) ($snapshot['sequence'] ?? 0) === (int) $version->version
                && hash_equals($previous->sha256, $snapshot['previous_document']['sha256'] ?? ''), 409, 'Referencias de adenda inconsistentes.');
            $rootSnapshot = $this->verifyOne($parent->signature, $verifyFiles, $verified, $customerId);
            if ($schema === 4) abort_unless(app(CanonicalContentSerializer::class)->serialize($snapshot['patient']) === app(CanonicalContentSerializer::class)->serialize($rootSnapshot['patient']), 409, 'La adenda debe conservar la identidad histórica del paciente.');
            $this->verifyOne($previous->signature, $verifyFiles, $verified, $customerId);
            $correction = $snapshot['correction'] ?? null;
            if (($snapshot['addendum_type'] ?? '') === 'rectification') {
                abort_unless($correction && (int) $version->rectifies_version_id === (int) $correction['document_id'] && filled($correction['reason'] ?? null), 409);
                $target = $reference((int) $version->rectifies_version_id);
                abort_unless($target->signature && hash_equals($target->sha256, $correction['document_sha256'] ?? ''), 409);
                $old = $this->verifyOne($target->signature, $verifyFiles, $verified, $customerId);
                $fields = $target->kind === 'original' ? ['chief_complaint', 'clinical_assessment', 'treatment_plan'] : ['content'];
                abort_unless(in_array($correction['field'] ?? '', $fields, true)
                    && ($old[$correction['field']] ?? null) === ($correction['previous_text'] ?? null), 409, 'La rectificación no conserva el texto anterior.');
            } else abort_unless($version->rectifies_version_id === null && $correction === null, 409);
        }
        if ($verifyFiles) app(SignedAttachmentStore::class)->verify($version);
        return $verified[$version->id] = $snapshot;
    }
}
