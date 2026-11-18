<?php

namespace Tests\Unit\Identification;

use App\Services\Identification\DocumentSet;
use App\Services\Identification\HolderKind;
use App\Services\Identification\IdentificationService;
use DateTimeImmutable;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class DocumentSetTest extends TestCase
{
    public function test_multiple_documents_and_countries_have_exactly_one_explicit_primary(): void
    {
        $service = new IdentificationService;
        $empty = new DocumentSet;
        $set = $empty->add('rut', $service->identify('CL', 'national_id', '123456785'), true)
            ->add('passport', $service->identify('AR', 'passport', '000Ab-12'));
        $this->assertCount(0, $empty->all());
        $this->assertCount(2, $set->all());
        $this->assertSame('rut', $set->primary()->reference);
        $changed = $set->withPrimary('passport');
        $this->assertSame('passport', $changed->primary()->reference);
        $this->assertSame('rut', $set->primary()->reference);
        $this->assertSame('000Ab-12', $changed->primary()->identifier->number->display);
    }

    public function test_retirement_keeps_original_content_date_and_previous_set_without_silent_replacement(): void
    {
        $document = (new IdentificationService)->identify('CL', 'national_id', '12.345.678-5');
        $set = (new DocumentSet)->add('rut', $document, true);
        $at = new DateTimeImmutable('2026-11-05T12:00:00+00:00');
        $retired = $set->retire('rut', $at);
        $this->assertNull($retired->primary());
        $this->assertCount(0, $retired->active());
        $this->assertCount(1, $retired->all());
        $this->assertSame($at, $retired->all()[0]->retiredAt);
        $this->assertSame($document, $retired->all()[0]->identifier);
        $this->assertTrue($set->primary()->isActive());
        $this->expectException(InvalidArgumentException::class);
        $retired->withPrimary('rut');
    }

    public function test_equivalent_rut_format_or_type_cannot_duplicate_a_persons_document(): void
    {
        $service = new IdentificationService;
        $set = (new DocumentSet)->add('first', $service->identify('CL', 'national_id', '123456785'));
        $this->expectException(InvalidArgumentException::class);
        $set->add('second', $service->identify('CL', 'tax_id', '12.345.678-5'));
    }

    public function test_same_number_different_country_or_namespace_is_not_silently_merged(): void
    {
        $service = new IdentificationService;
        $set = (new DocumentSet)->add('ar', $service->identify('AR', 'national_id', '001234'))
            ->add('pe', $service->identify('PE', 'national_id', '001234'))
            ->add('passport', $service->identify('AR', 'passport', '001234'));
        $this->assertCount(3, $set->active());
        $this->assertNull($set->primary());
        $this->assertFalse($service->identify('AR', 'national_id', '001234')->sameIdentifierAs($service->identify('AR', 'national_id', '1234')));
        $this->assertFalse($service->identify('AR', 'national_id', 'Ab123')->sameIdentifierAs($service->identify('AR', 'national_id', 'AB123')));
    }

    public function test_document_set_does_not_accept_personal_identity_for_legal_entity(): void
    {
        $this->expectException(InvalidArgumentException::class);
        (new DocumentSet(HolderKind::LegalEntity))->add('foreign', (new IdentificationService)->identify('AR', 'passport', 'ABC123'));
    }

    public function test_a_reference_cannot_be_overwritten_and_retired_history_is_not_readded(): void
    {
        $service = new IdentificationService;
        $document = $service->identify('GB', 'passport', '001234');
        $set = (new DocumentSet)->add('first', $document)->retire('first', new DateTimeImmutable('2026-11-05'));
        foreach (['first', 'new-reference'] as $reference) {
            try {
                $set->add($reference, $document);
                $this->fail('Historical records cannot be overwritten');
            } catch (InvalidArgumentException) {
                $this->assertCount(1, $set->all());
            }
        }
        $this->expectException(InvalidArgumentException::class);
        $set->retire('first', new DateTimeImmutable('2026-11-06'));
    }

    public function test_unknown_primary_and_empty_reference_are_rejected(): void
    {
        $set = new DocumentSet;
        try {
            $set->withPrimary('missing');
            $this->fail('No implicit primary');
        } catch (InvalidArgumentException) {
            $this->assertNull($set->primary());
        }
        $this->expectException(InvalidArgumentException::class);
        $set->add('', (new IdentificationService)->identify('AR', 'national_id', '00123'));
    }

    public function test_validated_results_are_readonly_and_never_verify_the_holder(): void
    {
        $document = (new IdentificationService)->identify('AR', 'passport', '00123');
        $this->assertFalse($document->holderIdentityVerified());
        $this->expectException(\Error::class);
        $document->number->normalized = 'different';
    }
}
