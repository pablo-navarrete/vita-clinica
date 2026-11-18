<?php
namespace App\Contracts;

interface EncounterSignatureProvider extends ElectronicSignatureMethod
{
    /** Returns mechanism metadata, never authentication secrets. */
    public function evidence(\App\Models\EncounterDocumentVersion $version, \App\Models\User $signer): array;
}
