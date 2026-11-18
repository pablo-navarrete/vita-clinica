<?php

namespace App\Services\Identification;

final class ChileanRutRule implements DocumentNumberRule
{
    public function validate(string $number): ValidatedNumber
    {
        $number = trim($number, ' ');
        if (! preg_match('/\A(?:[0-9]{1,8}-?[0-9kK]|[0-9]{1,3}(?:\.[0-9]{3}){1,2}-[0-9kK])\z/D', $number)) {
            throw new InvalidIdentification('number', 'Ingresa un RUT chileno con un formato válido.');
        }
        $compact = strtoupper(str_replace(['.', '-'], '', $number));
        $body = substr($compact, 0, -1);
        if (! preg_match('/\A[1-9][0-9]{0,7}\z/D', $body)) {
            throw new InvalidIdentification('number', 'El cuerpo del RUT debe tener entre 1 y 8 dígitos, sin ceros iniciales.');
        }
        $sum = 0;
        $weight = 2;
        for ($i = strlen($body) - 1; $i >= 0; $i--) {
            $sum += (int) $body[$i] * $weight;
            $weight = $weight === 7 ? 2 : $weight + 1;
        }
        $remainder = 11 - ($sum % 11);
        $expected = match ($remainder) {
            11 => '0', 10 => 'K', default => (string) $remainder
        };
        if (substr($compact, -1) !== $expected) {
            throw new InvalidIdentification('number', 'El dígito verificador del RUT no es válido.');
        }
        $display = preg_replace('/(?<=[0-9])(?=(?:[0-9]{3})+$)/', '.', $body).'-'.$expected;

        return new ValidatedNumber($compact, $display, ValidationLevel::Checksum, 'cl-rut-v1');
    }
}
