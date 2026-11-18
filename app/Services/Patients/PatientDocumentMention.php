<?php

namespace App\Services\Patients;

/** Explicit document syntax only; foreign country and type are never inferred from a number. */
final class PatientDocumentMention
{
    private const FOREIGN = '/\b(pasaporte|documento nacional|identificador tributario)\s+([a-z]{2})\s*[: ]\s*([^\s,;]+)/iu';
    private const RUT = '/\b(?:\d{1,2}\.\d{3}\.\d{3}|\d{7,8})-?[\dkK]\b/';

    public function strip(string $text): string
    {
        return preg_replace([self::FOREIGN, self::RUT], '', $text);
    }

    public function parse(string $text): ?array
    {
        $data = null;
        if (preg_match(self::FOREIGN, $text, $match)) {
            $data = ['identification_country' => strtoupper($match[2]), 'identification_type' => match(mb_strtolower($match[1])) {
                'pasaporte' => 'passport', 'documento nacional' => 'national_id', default => 'tax_id',
            }, 'identification_number' => $match[3]];
        } elseif (preg_match(self::RUT, $text, $match)) {
            $data = ['identification_country' => 'CL', 'identification_type' => 'national_id', 'identification_number' => $match[0]];
        }
        if (!$data) return null;
        $document = app(PatientRegistrationService::class)->document($data);
        return $data + ['display' => $document->number->display];
    }
}
