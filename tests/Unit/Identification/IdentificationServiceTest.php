<?php

namespace Tests\Unit\Identification;

use App\Services\Identification\DocumentType;
use App\Services\Identification\HolderKind;
use App\Services\Identification\IdentificationService;
use App\Services\Identification\InvalidIdentification;
use App\Services\Identification\ValidationLevel;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class IdentificationServiceTest extends TestCase
{
    public function test_laravel_container_resolves_the_service_without_booting_an_application(): void
    {
        $container = new \Illuminate\Container\Container;
        $service = $container->make(IdentificationService::class);
        $this->assertInstanceOf(IdentificationService::class, $service);
        $this->assertSame('12.345.678-5', $service->identify('CL', 'national_id', '123456785')->number->display);
    }

    #[DataProvider('validRuts')]
    public function test_rut_normalizes_formats_and_validates_modulo_eleven(string $input, string $compact, string $display): void
    {
        $document = (new IdentificationService)->identify('cl', DocumentType::NationalId, $input);
        $this->assertSame($input, $document->original);
        $this->assertSame($compact, $document->number->normalized);
        $this->assertSame($display, $document->number->display);
        $this->assertSame(ValidationLevel::Checksum, $document->number->level);
        $this->assertSame('cl-rut-v1', $document->number->ruleVersion);
        $this->assertFalse($document->holderIdentityVerified());
    }

    public static function validRuts(): array
    {
        return [
            ['123456785', '123456785', '12.345.678-5'],
            ['12345678-5', '123456785', '12.345.678-5'],
            ['12.345.678-5', '123456785', '12.345.678-5'],
            [' 12.345.678-5 ', '123456785', '12.345.678-5'],
            ['12.345.678-5', '123456785', '12.345.678-5'],
            ['6-k', '6K', '6-K'], ['6K', '6K', '6-K'], ['14-0', '140', '14-0'], ['1-9', '19', '1-9'],
        ];
    }

    #[DataProvider('invalidRuts')]
    public function test_invalid_ruts_are_rejected_without_silent_character_removal(string $input): void
    {
        $this->expectException(InvalidIdentification::class);
        (new IdentificationService)->identify('CL', 'national_id', $input);
    }

    public static function invalidRuts(): array
    {
        return array_map(fn ($value) => [$value], [
            '', '12345678-4', '12.345.678-K', '0-0', '00000000-0', '012345678-5', '01-9',
            '12..345.678-5', '12.345678-5', '12-345678-5', '12 345 678-5', '12.345.678/5',
            '12.345.678-A', '12.345.678-5x', '123456789-0', '12345678', "12.345.678-5\n", '１２３４５６７８５',
        ]);
    }

    #[DataProvider('foreignDocuments')]
    public function test_foreign_numbers_preserve_significant_characters(string $country, string $type, string $number): void
    {
        $document = (new IdentificationService)->identify($country, $type, $number);
        $this->assertSame($number, $document->original);
        $this->assertSame($number, $document->number->normalized);
        $this->assertSame($number, $document->number->display);
        $this->assertSame(ValidationLevel::Basic, $document->number->level);
        $this->assertSame('basic-document-v1', $document->number->ruleVersion);
        $this->assertFalse($document->holderIdentityVerified());
    }

    public static function foreignDocuments(): array
    {
        return [
            ['AR', 'national_id', '00123456'], ['PE', 'national_id', '00001234'],
            ['US', 'passport', '00Ab-123/45'], ['FR', 'tax_id', '00.AB  12'],
            ['DE', 'passport', 'aB001'], ['JP', 'national_id', '東京都0012'],
            ['GR', 'national_id', 'ΑΒ00123'], ['CI', 'national_id', "A'001"],
            ['PT', 'national_id', 'A’001'], ['AR', 'national_id', 'é001'],
            ['AR', 'national_id', "e\u{0301}001"], ['BR', 'tax_id', '00.123.456/0001-00'],
        ];
    }

    public function test_basic_rule_does_not_guess_country_or_apply_chilean_rut_to_a_passport(): void
    {
        $service = new IdentificationService;
        $foreign = $service->identify('AR', 'national_id', '123456784');
        $passport = $service->identify('CL', 'passport', '00AB123');
        $this->assertSame(ValidationLevel::Basic, $foreign->number->level);
        $this->assertSame('AR', $foreign->definition->country);
        $this->assertSame(ValidationLevel::Basic, $passport->number->level);
        $this->assertSame('00AB123', $passport->number->normalized);
        $this->assertSame(' 001Ab ', $service->identify('GB', 'passport', ' 001Ab ')->original);
        $this->assertSame('001Ab', $service->identify('GB', 'passport', ' 001Ab ')->number->normalized);
    }

    #[DataProvider('unsafeDocuments')]
    public function test_foreign_validation_rejects_unsafe_or_oversized_input(mixed $input): void
    {
        $this->expectException(InvalidIdentification::class);
        (new IdentificationService)->identify('AR', 'national_id', $input);
    }

    public static function unsafeDocuments(): array
    {
        return array_map(fn ($value) => [$value], [
            '', '   ', '-', '.', 'A<script>1', 'A&123', 'A"123', 'A\\123',
            "A\0B", "A\nB", "A\rB", "A\tB", "\nA001", "A\u{202E}001", "A\u{200B}001",
            "A\u{2028}001", "A\u{2029}001", "\xC3\x28", str_repeat('A', 101), str_repeat('A', 129),
            123456, null, ['00123'], true,
        ]);
    }

    public function test_unicode_length_boundary_is_characters_and_errors_do_not_echo_the_number(): void
    {
        $service = new IdentificationService;
        $this->assertSame(str_repeat('é', 100), $service->identify('FR', 'national_id', str_repeat('é', 100))->number->normalized);
        try {
            $service->identify('CL', 'national_id', '12345678-4');
            $this->fail('Invalid checksum must fail');
        } catch (InvalidIdentification $error) {
            $this->assertSame('number', $error->field);
            $this->assertStringNotContainsString('12345678', $error->getMessage());
        }
    }

    public function test_personal_and_corporate_tax_ids_share_algorithm_without_proving_entity_or_person(): void
    {
        $service = new IdentificationService;
        $person = $service->identify('CL', 'tax_id', '12345678-5', HolderKind::NaturalPerson);
        $company = $service->identify('CL', 'tax_id', '12345678-5', HolderKind::LegalEntity);
        $this->assertSame($person->number->normalized, $company->number->normalized);
        $this->assertSame('RUT tributario', $company->definition->label);
        $this->assertFalse($person->sameIdentifierAs($company));
        $this->assertFalse($company->holderIdentityVerified());
        // No claim is made that this actual number belongs to a legal entity.
    }
}
