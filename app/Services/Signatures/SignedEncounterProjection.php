<?php
namespace App\Services\Signatures;

use App\Models\Encounter;
use App\Models\User;
use App\Models\ClinicBranch;
use App\Models\Specialty;

/** Applies historical values to an in-memory read model, never to persisted rows. */
class SignedEncounterProjection
{
    public function apply(Encounter $encounter, bool $verifyFiles = false): void
    {
        if (!$encounter->signature) return;
        $snapshot = app(EncounterSignatureVerifier::class)->verify($encounter->signature, $verifyFiles);
        foreach (['chief_complaint', 'clinical_assessment', 'treatment_plan', 'modality'] as $key) $encounter->setAttribute($key, $snapshot[$key]);
        $encounter->setRelation('professional', (new User)->forceFill($snapshot['professional']));
        $encounter->setRelation('clinicBranch', (new ClinicBranch)->forceFill($snapshot['branch']));
        $encounter->setRelation('specialty', $snapshot['specialty']['id'] ? (new Specialty)->forceFill($snapshot['specialty']) : null);
        $encounter->setRelation('observations', collect());
    }
}
