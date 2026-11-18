<?php

namespace App\Services\Identification;

final class DocumentTypeRegistry
{
    public function __construct(private CountryCatalog $countries = new CountryCatalog) {}

    public function definition(string $country, DocumentType|string $type, HolderKind|string $holderKind): DocumentTypeDefinition
    {
        $country = $this->countries->normalizeCode($country);
        $type = is_string($type) ? DocumentType::tryFrom($type) : $type;
        $holderKind = is_string($holderKind) ? HolderKind::tryFrom($holderKind) : $holderKind;
        if (! $holderKind) {
            throw new InvalidIdentification('holder_kind', 'Selecciona persona natural o persona jurídica.');
        }
        if (! $type || ($holderKind === HolderKind::LegalEntity && $type !== DocumentType::TaxId)) {
            throw new InvalidIdentification('document_type', 'El tipo de documento no corresponde al titular.');
        }

        $isRut = $country === 'CL' && $type !== DocumentType::Passport;
        $label = match ($type) {
            DocumentType::NationalId => $country === 'CL' ? 'RUT / RUN' : 'Documento nacional',
            DocumentType::Passport => 'Pasaporte',
            DocumentType::TaxId => $country === 'CL' ? 'RUT tributario' : 'Identificador tributario',
        };

        return new DocumentTypeDefinition($country, $type, $holderKind, $label,
            $isRut ? 'CL:RUT' : $country.':'.strtoupper($type->value), $isRut ? 'cl_rut' : 'basic');
    }

    /** Availability means VITA accepts this category, not that a country issues every category. */
    public function available(string $country, HolderKind|string $holderKind = HolderKind::NaturalPerson): array
    {
        $country = $this->countries->normalizeCode($country);
        $holderKind = is_string($holderKind) ? HolderKind::tryFrom($holderKind) : $holderKind;
        if (! $holderKind) {
            throw new InvalidIdentification('holder_kind', 'Selecciona persona natural o persona jurídica.');
        }
        $types = $holderKind === HolderKind::LegalEntity ? [DocumentType::TaxId] : DocumentType::cases();

        return array_map(fn ($type) => $this->definition($country, $type, $holderKind), $types);
    }
}
