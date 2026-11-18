<?php

namespace Tests\Unit\Identification;

use App\Services\Identification\History\HistoricalDocumentClassifier;
use App\Services\Identification\History\HistoricalIdentifierDiagnostic;
use PHPUnit\Framework\TestCase;

class HistoricalIdentifierDiagnosticTest extends TestCase
{
    public function test_classifier_never_infers_a_country_even_with_a_valid_chilean_checksum(): void
    {
        $classifier = new HistoricalDocumentClassifier;
        $valid = $classifier->classify('12.345.678-5');
        $this->assertSame('rut_candidate_checksum_valid', $valid['classification']);
        $this->assertSame('123456785', $valid['candidate_normalized']);
        $this->assertNull($valid['confirmed_country']);
        $this->assertSame('rut_candidate_invalid', $classifier->classify('12.345.678-4')['classification']);
        $this->assertSame('unclassified', $classifier->classify('AB-00123')['classification']);
        $this->assertSame('unsafe_legacy', $classifier->classify("A\n00123")['classification']);
        $this->assertSame('empty', $classifier->classify(null)['classification']);
    }

    public function test_reports_equivalent_formats_duplicates_and_conflicts_without_numbers_or_owner_ids(): void
    {
        $sources = ['patients' => [
            ['id' => 98765, 'customer_id' => 1, 'rut' => '123456785'], ['id' => 98766, 'customer_id' => 1, 'rut' => '12.345.678-5'],
            ['id' => 98767, 'customer_id' => 2, 'rut' => '12345678-5'], ['id' => 98768, 'customer_id' => 1, 'rut' => '001Ab-12'],
            ['id' => 98769, 'customer_id' => 1, 'rut' => '123456784'], ['id' => 98770, 'customer_id' => 1, 'rut' => '12.345.678-4'],
        ]];
        $diagnostic = new HistoricalIdentifierDiagnostic;
        $before = serialize($sources);
        $report = $diagnostic->summarize($sources);
        $second = $diagnostic->summarize($sources);
        $this->assertSame($report, $second);
        $this->assertSame($before, serialize($sources));
        $this->assertFalse($report['country_inference']);
        $stats = $report['sources']['patients'];
        $this->assertSame(2, $stats['duplicate_candidate_groups']);
        $this->assertSame(2, $stats['equivalent_format_groups']);
        $this->assertSame(1, $stats['potential_patient_claim_conflicts']);
        $json = json_encode($report);
        foreach (['123456785', '12.345.678-5', '001Ab-12', '98765'] as $private) {
            $this->assertStringNotContainsString($private, $json);
        }
    }

    public function test_customer_and_user_reuse_is_advisory_not_a_global_uniqueness_rule(): void
    {
        $report = (new HistoricalIdentifierDiagnostic)->summarize([
            'customers' => [['id' => 1, 'rut' => '123456785'], ['id' => 2, 'rut' => '12.345.678-5']],
            'users' => [['id' => 3, 'customer_id' => 1, 'rut' => '123456785'], ['id' => 4, 'customer_id' => 1, 'rut' => '12.345.678-5'],
                ['id' => 5, 'customer_id' => 2, 'rut' => '123456785'], ['id' => 6, 'customer_id' => null, 'rut' => '123456785']],
        ]);
        foreach (['customers', 'users'] as $table) {
            $this->assertTrue($report['sources'][$table]['duplicates_are_advisory']);
            $this->assertSame(0, $report['sources'][$table]['potential_patient_claim_conflicts']);
            $this->assertSame(1, $report['sources'][$table]['duplicate_candidate_groups']);
        }
    }
}
