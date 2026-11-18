<?php

namespace App\Services\Signatures;

use App\Contracts\ElectronicSignatureMethod;

class SignatureMethodRegistry
{
    /** Future methods must explicitly implement the contract and be registered here. */
    public function available(): array
    {
        $method = app(VitaSimpleSignatureMethod::class);

        return [$method->key() => $method];
    }

    public function get(string $key): ElectronicSignatureMethod
    {
        return $this->available()[$key] ?? throw new \InvalidArgumentException('Unavailable signature method.');
    }
}
