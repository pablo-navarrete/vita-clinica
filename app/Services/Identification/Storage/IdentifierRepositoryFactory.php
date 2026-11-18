<?php

namespace App\Services\Identification\Storage;

use Illuminate\Support\Facades\DB;

final class IdentifierRepositoryFactory
{
    public function forNewPatient(\App\Models\Patient $patient): IdentifierRepository
    {
        return new IdentifierRepository(DB::connection(), new IdentifierCrypto(config('identification')), new PatientIdentifierAccess($patient));
    }

    public function forClinicalPatientRead(): IdentifierRepository
    {
        return new IdentifierRepository(DB::connection(), new IdentifierCrypto(config('identification')), new PatientIdentifierAccess);
    }
    public function make(): IdentifierRepository
    {
        // Not automatically resolved anywhere in current VITA flows.
        return new IdentifierRepository(DB::connection(), new IdentifierCrypto(config('identification')), new VitaIdentifierAuthorizer);
    }
}
