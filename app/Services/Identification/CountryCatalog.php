<?php

namespace App\Services\Identification;

use Illuminate\Support\Str;

final class CountryCatalog
{
    private array $definition;

    public function __construct(?array $definition = null)
    {
        // Works with Laravel's container and in pure unit tests, without an application or DB.
        $this->definition = $definition ?? require dirname(__DIR__, 3).'/config/countries.php';
    }

    public function defaultCountry(): string
    {
        return $this->definition['default'];
    }

    public function normalizeCode(string $code): string
    {
        $code = strtoupper(trim($code, ' '));
        if (! preg_match('/\A[A-Z]{2}\z/D', $code) || ! isset($this->definition['countries'][$code])) {
            throw new InvalidIdentification('issuing_country', 'Selecciona un país emisor válido del catálogo ISO.');
        }

        return $code;
    }

    public function name(string $code): string
    {
        return $this->definition['countries'][$this->normalizeCode($code)];
    }

    /** @return array<string, string> Codes and Spanish names, sorted by name. */
    public function all(): array
    {
        $countries = $this->definition['countries'];
        uasort($countries, fn (string $a, string $b) => strcmp($this->fold($a), $this->fold($b)));

        return $countries;
    }

    /** Search names or ISO codes without modifying any document numbers. */
    public function search(string $term): array
    {
        $term = $this->fold(trim($term));

        return array_filter($this->all(), fn (string $name, string $code) => $term === '' || str_contains($this->fold($name), $term) || str_contains(strtolower($code), $term), ARRAY_FILTER_USE_BOTH);
    }

    public function source(): array
    {
        return $this->definition['source'];
    }

    private function fold(string $value): string
    {
        return mb_strtolower(Str::ascii($value), 'UTF-8');
    }
}
