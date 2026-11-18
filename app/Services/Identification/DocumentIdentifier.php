<?php

namespace App\Services\Identification;

final class DocumentIdentifier
{
    public function __construct(
        public readonly DocumentTypeDefinition $definition,
        public readonly string $original,
        public readonly ValidatedNumber $number,
    ) {}

    /** Document syntax/checksum does not prove who owns it. */
    public function holderIdentityVerified(): bool
    {
        return false;
    }

    public function sameIdentifierAs(self $other): bool
    {
        return $this->definition->country === $other->definition->country
            && $this->definition->namespace === $other->definition->namespace
            && $this->definition->holderKind === $other->definition->holderKind
            && $this->number->normalized === $other->number->normalized;
    }
}
