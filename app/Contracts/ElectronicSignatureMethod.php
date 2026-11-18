<?php

namespace App\Contracts;

/** Describes configuration capabilities; this contract does not sign clinical content. */
interface ElectronicSignatureMethod
{
    public function key(): string;

    public function label(): string;
}
