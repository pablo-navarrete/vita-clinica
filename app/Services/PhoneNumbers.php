<?php

namespace App\Services;

use libphonenumber\{NumberParseException, PhoneNumberFormat, PhoneNumberUtil};

/** Numbering-plan validation only; no external requests or ownership checks. */
final class PhoneNumbers
{
    public const MESSAGE = 'Ingresa un número de teléfono válido para el país seleccionado.';

    public static function isField(string $attribute): bool
    {
        return (bool) preg_match('/(?:^|\.)(?:phone|[a-z_]+_phone|reply_whatsapp)$/', $attribute);
    }

    public function parse(mixed $value): ?\libphonenumber\PhoneNumber
    {
        if (!is_string($value) || strlen($value) > 80 || !preg_match('/\A\s*\+?[0-9\s().\-]+\s*\z/u', $value)) return null;
        $digits = preg_replace('/[\s().\-]/u', '', $value);
        if (str_starts_with($digits, '00')) $digits = '+'.substr($digits, 2);
        $util = PhoneNumberUtil::getInstance();
        // Explicit codes have priority. Keep Chilean national numbers usable in existing forms.
        $candidates = str_starts_with($digits, '+') ? [[$digits, null]]
            : (strlen($digits) === 9 ? [[$digits, 'CL'], ['+'.$digits, null]] : [['+'.$digits, null], [$digits, 'CL']]);
        foreach ($candidates as [$number, $region]) {
            try {
                $parsed = $util->parse($number, $region);
                if (!$parsed->hasExtension() && $util->isValidNumber($parsed)) return $parsed;
            } catch (NumberParseException) { /* Try the Chilean national format if applicable. */ }
        }
        return null;
    }

    public function normalize(mixed $value): ?string
    {
        $number = $this->parse($value);
        return $number ? PhoneNumberUtil::getInstance()->format($number, PhoneNumberFormat::E164) : null;
    }

    public function country(mixed $value): ?string
    {
        $number = $this->parse($value);
        if (!$number) return null;
        $region = PhoneNumberUtil::getInstance()->getRegionCodeForNumber($number);
        return $region && $region !== '001' ? $region : null;
    }

    public function normalizeFields(array $data): array
    {
        foreach ($data as $key => $value) {
            if (is_array($value)) $data[$key] = $this->normalizeFields($value);
            elseif (self::isField((string)$key) && is_string($value) && ($normalized = $this->normalize($value))) $data[$key] = $normalized;
        }
        return $data;
    }
}
