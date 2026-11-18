<?php

namespace App\Services\Identification;

final class DocumentTypeDefinition
{
    public function __construct(
        public readonly string $country,
        public readonly DocumentType $type,
        public readonly HolderKind $holderKind,
        public readonly string $label,
        public readonly string $namespace,
        public readonly string $rule,
    ) {}
}
