<?php
namespace App\Services\Signatures;

use App\Models\Encounter;

class EncounterAddendumReader
{
    public function read(Encounter $encounter, bool $verifyFiles = false): array
    {
        $signatures = $encounter->addendumSignatures;
        $snapshots = app(EncounterSignatureVerifier::class)->verifyMany($signatures, $verifyFiles);
        return $signatures->sortBy(fn ($signature) => $signature->documentVersion->version)->map(fn ($signature) => [
            'signature' => $signature, 'snapshot' => $snapshots[$signature->id],
        ])->values()->all();
    }
}
