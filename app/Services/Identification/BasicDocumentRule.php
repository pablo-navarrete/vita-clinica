<?php

namespace App\Services\Identification;

final class BasicDocumentRule implements DocumentNumberRule
{
    public function validate(string $number): ValidatedNumber
    {
        // Preserve case, leading zeros, Unicode, punctuation and internal spacing.
        // No transliteration or universal foreign checksum assumptions.
        $number = trim($number, ' ');
        if (! mb_check_encoding($number, 'UTF-8') || mb_strlen($number, 'UTF-8') < 1
            || mb_strlen($number, 'UTF-8') > 100
            || ! preg_match("/\A[\p{L}\p{N}][\p{L}\p{M}\p{N}\p{Zs}.'’\/\-]*\z/uD", $number)
            || ! preg_match('/[\p{L}\p{M}\p{N}]\z/uD', $number)) {
            throw new InvalidIdentification('number', 'Ingresa un documento de hasta 100 caracteres, con letras, números o separadores admitidos.');
        }

        return new ValidatedNumber($number, $number, ValidationLevel::Basic, 'basic-document-v1');
    }
}
