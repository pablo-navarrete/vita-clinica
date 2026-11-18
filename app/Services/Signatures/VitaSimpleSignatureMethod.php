<?php

namespace App\Services\Signatures;

class VitaSimpleSignatureMethod implements \App\Contracts\EncounterSignatureProvider
{
    public function evidence(\App\Models\EncounterDocumentVersion $version, \App\Models\User $signer): array
    {
        return ['method' => $this->key(), 'mechanism_version' => 'vita-fes-v1', 'authentication_method' => 'password'];
    }
    public function key(): string
    {
        return 'fes_vita';
    }

    public function label(): string
    {
        return 'Firma Electrónica Simple (VITA)';
    }
}
