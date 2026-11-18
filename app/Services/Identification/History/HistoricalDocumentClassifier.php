<?php

namespace App\Services\Identification\History;

use App\Services\Identification\IdentificationService;
use App\Services\Identification\InvalidIdentification;

final class HistoricalDocumentClassifier
{
    public function __construct(private IdentificationService $identification = new IdentificationService) {}

    /** Candidate normalization is an internal diagnostic hint, never a confirmed issuing country. */
    public function classify(?string $value): array
    {
        if ($value === null || trim($value, ' ') === '') {
            return ['classification' => 'empty', 'candidate_normalized' => null, 'confirmed_country' => null];
        }
        if (! mb_check_encoding($value, 'UTF-8') || preg_match('/[\p{C}\p{Zl}\p{Zp}]/u', $value)) {
            return ['classification' => 'unsafe_legacy', 'candidate_normalized' => null, 'confirmed_country' => null];
        }
        if (! preg_match('/\A[0-9kK. -]+\z/D', $value)) {
            return ['classification' => 'unclassified', 'candidate_normalized' => null, 'confirmed_country' => null];
        }
        try {
            $candidate = $this->identification->identify('CL', 'national_id', $value);

            return ['classification' => 'rut_candidate_checksum_valid', 'candidate_normalized' => $candidate->number->normalized, 'confirmed_country' => null];
        } catch (InvalidIdentification) {
            $structural = preg_match('/\A(?:[0-9]{1,8}-?[0-9kK]|[0-9]{1,3}(?:\.[0-9]{3}){1,2}-[0-9kK])\z/D', trim($value, ' '));

            return ['classification' => 'rut_candidate_invalid', 'candidate_normalized' => $structural ? strtoupper(str_replace(['.', '-', ' '], '', $value)) : null, 'confirmed_country' => null];
        }
    }
}
