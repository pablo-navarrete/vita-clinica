<?php

namespace App\Services\Identification;

final class DocumentIdentityPresenter
{
    public function fhirIdentifiers(array $identity): array
    {
        $doc = $identity['document'] ?? null;
        if (! $doc || ! filled($doc['normalized'] ?? null)) return [];
        return [['use' => 'usual', 'system' => 'https://vita-clinica.cl/fhir/NamingSystem/identity/'.rawurlencode($doc['country']).'/'.rawurlencode($doc['type']),
            'value' => $doc['normalized'], 'type' => ['text' => $doc['namespace']]]];
    }
    public function label(array $identity, bool $masked = false): string
    {
        $doc = $identity['document'] ?? null;
        $number = $doc['display'] ?? $identity['rut'] ?? null;
        if (! filled($number)) return 'Identificación no registrada';
        $type = ! $doc || ($doc['type'] ?? '') === 'legacy_rut' ? 'Documento histórico' :
            (($doc['namespace'] ?? '') === 'CL:RUT' ? 'RUT' : match ($doc['type'] ?? '') {
                'passport' => 'Pasaporte', 'national_id' => 'Documento nacional', 'tax_id' => 'Identificador tributario', default => 'Documento',
            });
        if ($masked) $number = '••••'.(mb_strlen((string) $number) > 4 ? mb_substr((string) $number, -4) : '');
        return $type.(! empty($doc['country']) ? ' · '.$doc['country'] : '').': '.$number;
    }
}
