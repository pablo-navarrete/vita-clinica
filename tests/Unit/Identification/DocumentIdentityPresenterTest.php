<?php

namespace Tests\Unit\Identification;

use App\Services\Identification\DocumentIdentityPresenter;
use PHPUnit\Framework\TestCase;

class DocumentIdentityPresenterTest extends TestCase
{
    public function test_general_display_never_reveals_a_complete_short_document(): void
    {
        $presenter = new DocumentIdentityPresenter;
        foreach (['1', '001', '0001'] as $number) {
            $label = $presenter->label(['document' => ['type' => 'passport', 'country' => 'AR', 'display' => $number]], true);
            $this->assertStringNotContainsString($number, $label);
        }
        $this->assertStringContainsString('••••5678', $presenter->label(['rut' => '0012345678'], true));
    }

    public function test_authorized_document_and_fhir_keep_significant_characters(): void
    {
        $identity = ['document' => ['type' => 'passport', 'country' => 'PE', 'namespace' => 'PE:PASSPORT', 'display' => '000Ab-12', 'normalized' => '000Ab-12']];
        $presenter = new DocumentIdentityPresenter;
        $this->assertStringContainsString('000Ab-12', $presenter->label($identity));
        $this->assertSame('000Ab-12', $presenter->fhirIdentifiers($identity)[0]['value']);
        $this->assertSame([], $presenter->fhirIdentifiers(['rut' => 'unclassified-history']));
    }
}
