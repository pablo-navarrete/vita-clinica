<?php

namespace Tests\Unit\Identification;

use App\Services\Identification\CountryCatalog;
use App\Services\Identification\DocumentType;
use App\Services\Identification\DocumentTypeRegistry;
use App\Services\Identification\HolderKind;
use App\Services\Identification\InvalidIdentification;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class DocumentTypeRegistryTest extends TestCase
{
    public function test_all_countries_support_personal_categories_and_distinct_tax_contexts(): void
    {
        $registry = new DocumentTypeRegistry;
        foreach (array_keys((new CountryCatalog)->all()) as $country) {
            $personal = $registry->available($country);
            $corporate = $registry->available($country, HolderKind::LegalEntity);
            $this->assertCount(3, $personal);
            $this->assertCount(1, $corporate);
            $this->assertSame(DocumentType::TaxId, $corporate[0]->type);
            $this->assertSame(HolderKind::LegalEntity, $corporate[0]->holderKind);
        }
    }

    public function test_chilean_personal_and_tax_rut_use_one_registry_namespace_and_passport_is_separate(): void
    {
        $registry = new DocumentTypeRegistry;
        $personal = $registry->definition('CL', 'national_id', 'natural_person');
        $tax = $registry->definition('CL', 'tax_id', 'natural_person');
        $passport = $registry->definition('CL', 'passport', 'natural_person');
        $this->assertSame('CL:RUT', $personal->namespace);
        $this->assertSame($personal->namespace, $tax->namespace);
        $this->assertSame('cl_rut', $tax->rule);
        $this->assertSame('basic', $passport->rule);
        $this->assertNotSame($personal->namespace, $passport->namespace);
    }

    #[DataProvider('invalidTypes')]
    public function test_country_type_and_holder_are_always_validated(string $country, string $type, string $holder): void
    {
        $this->expectException(InvalidIdentification::class);
        (new DocumentTypeRegistry)->definition($country, $type, $holder);
    }

    public static function invalidTypes(): array
    {
        return [['CL', 'passport', 'legal_entity'], ['CL', 'national_id', 'legal_entity'],
            ['AR', 'passport', 'legal_entity'], ['ZZ', 'tax_id', 'natural_person'],
            ['CL', 'made_up_type', 'natural_person'], ['CL', 'tax_id', 'company'], ['CL', '', 'natural_person']];
    }
}
