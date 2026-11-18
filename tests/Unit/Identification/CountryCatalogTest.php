<?php

namespace Tests\Unit\Identification;

use App\Services\Identification\CountryCatalog;
use App\Services\Identification\InvalidIdentification;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class CountryCatalogTest extends TestCase
{
    public function test_complete_local_catalog_has_spanish_names_and_chile_default(): void
    {
        $catalog = new CountryCatalog;
        $this->assertCount(249, $catalog->all());
        $this->assertSame('CL', $catalog->defaultCountry());
        $this->assertSame('Chile', $catalog->name(' cl '));
        $this->assertSame('España', $catalog->name('ES'));
        $this->assertSame('Estados Unidos', $catalog->name('US'));
        foreach ($catalog->all() as $code => $name) {
            $this->assertMatchesRegularExpression('/^[A-Z]{2}$/D', $code);
            $this->assertNotSame('', $name);
            $this->assertTrue(mb_check_encoding($name, 'UTF-8'));
        }
        $this->assertSame('ISO 3166-1 alpha-2', $catalog->source()['standard']);
        $this->assertStringContainsString('/blob/', $catalog->source()['iso_codes']);
    }

    public function test_search_finds_names_without_accents_and_codes_without_a_database(): void
    {
        $catalog = new CountryCatalog;
        $this->assertSame(['ES' => 'España'], $catalog->search(' espana '));
        $this->assertSame(['MX' => 'México'], $catalog->search('MEXICO'));
        $this->assertArrayHasKey('CL', $catalog->search('cl'));
        $this->assertSame([], $catalog->search('not-a-country'));
        $this->assertCount(249, $catalog->search(''));
    }

    #[DataProvider('unknownCountries')]
    public function test_reserved_unknown_and_non_alpha_two_values_are_not_assigned_countries(string $code): void
    {
        $this->expectException(InvalidIdentification::class);
        (new CountryCatalog)->normalizeCode($code);
    }

    public static function unknownCountries(): array
    {
        return array_map(fn ($value) => [$value], ['', 'CHL', 'Chile', 'ZZ', 'EU', 'UK', 'XK', 'AC', 'AA', '12', 'C-L', "CL\0"]);
    }
}
