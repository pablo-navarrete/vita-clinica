<?php

namespace App\Services\Identification;

final class IdentificationService
{
    public function __construct(private DocumentTypeRegistry $types = new DocumentTypeRegistry) {}

    public function identify(string $country, DocumentType|string $type, mixed $number,
        HolderKind|string $holderKind = HolderKind::NaturalPerson): DocumentIdentifier
    {
        $definition = $this->types->definition($country, $type, $holderKind);
        // Reject controls (including bidi/zero-width controls), newlines and oversized input
        // before trimming, so unsafe input is never silently repaired.
        if (! is_string($number) || ! mb_check_encoding($number, 'UTF-8') || mb_strlen($number, 'UTF-8') > 128
            || preg_match('/[\p{C}\p{Zl}\p{Zp}]/u', $number)) {
            throw new InvalidIdentification('number', 'El documento contiene caracteres no admitidos o supera el tamaño permitido.');
        }
        $rule = match ($definition->rule) {
            'cl_rut' => new ChileanRutRule,
            'basic' => new BasicDocumentRule,
        };

        return new DocumentIdentifier($definition, $number, $rule->validate($number));
    }
}
