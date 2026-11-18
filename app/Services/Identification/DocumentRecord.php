<?php

namespace App\Services\Identification;

use DateTimeImmutable;

/** In-memory lifecycle record, not a database model or a verified identity. */
final class DocumentRecord
{
    public function __construct(
        public readonly string $reference,
        public readonly DocumentIdentifier $identifier,
        public readonly ?DateTimeImmutable $retiredAt = null,
    ) {}

    public function isActive(): bool
    {
        return $this->retiredAt === null;
    }
}
