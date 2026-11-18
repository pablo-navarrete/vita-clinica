<?php
namespace App\Services\Signatures;

use App\Contracts\EncounterSignatureProvider;
use App\Models\{Encounter, EncounterDocumentVersion, EncounterSignature, EncounterSigningOperation, User};

/** Invoked only inside the caller's locked signing transaction, after authorization and reauthentication. */
class SignedDocumentWriter
{
    public function persist(EncounterSigningOperation $op, Encounter $encounter, User $user, array $snapshot,
        string $payload, array $manifest, string $kind, int $sequence, string $acceptanceVersion, string $acceptance,
        ?int $parentId = null, ?int $rectifiesId = null): EncounterSignature
    {
        $version = EncounterDocumentVersion::create([
            'customer_id' => $op->customer_id, 'encounter_id' => $encounter->id, 'patient_id' => $encounter->patient_id,
            'clinic_branch_id' => $encounter->clinic_branch_id, 'version' => $sequence, 'kind' => $kind,
            'parent_version_id' => $parentId, 'rectifies_version_id' => $rectifiesId,
            'schema_version' => $snapshot['schema_version'], 'payload' => $payload,
            'sha256' => $op->content_hash, 'attachment_manifest' => $manifest,
        ]);
        $provider = app(SignatureMethodRegistry::class)->get('fes_vita');
        if (!$provider instanceof EncounterSignatureProvider) throw new \LogicException('Unavailable signing provider.');
        $at = now('UTC');
        $visuals = collect($snapshot['visual_assets'])->keyBy('kind');
        $signature = EncounterSignature::create($provider->evidence($version, $user) + [
            'operation_id' => $op->id, 'customer_id' => $op->customer_id, 'encounter_id' => $encounter->id,
            'document_version_id' => $version->id, 'user_id' => $user->id,
            'signature_asset_id' => $visuals->get('signature')['id'] ?? null, 'stamp_asset_id' => $visuals->get('stamp')['id'] ?? null,
            'acceptance_version' => $acceptanceVersion, 'accepted' => true,
            'identity' => $snapshot['professional'] + ['acceptance_text' => $acceptance],
            'content_hash' => $op->content_hash, 'authenticated_at' => $at, 'signed_at' => $at,
        ]);
        $op->update(['status' => 'committed']);
        return $signature;
    }
}
