<?php

namespace App\Services\Identification\History;

use InvalidArgumentException;

final class HistoricalIdentifierDiagnostic
{
    public function __construct(private HistoricalDocumentClassifier $classifier = new HistoricalDocumentClassifier) {}

    /** Iterables may be lazy database readers. No raw document or stable digest leaves the summary. */
    public function summarize(array $sources): array
    {
        $secret = random_bytes(32);
        $report = ['schema_version' => 1, 'mode' => 'read_only', 'country_inference' => false, 'sources' => []];
        foreach ($sources as $table => $rows) {
            if (! in_array($table, ['customers', 'users', 'patients'], true)) {
                throw new InvalidArgumentException('Fuente histórica no admitida.');
            }
            $counts = array_fill_keys(['empty', 'unsafe_legacy', 'unclassified', 'rut_candidate_checksum_valid', 'rut_candidate_invalid'], 0);
            $groups = [];
            $total = 0;
            $deleted = 0;
            foreach ($rows as $row) {
                $row = (array) $row;
                $total++;
                $deleted += ! empty($row['deleted_at']) ? 1 : 0;
                $value = $row['rut'] ?? null;
                $result = $this->classifier->classify($value);
                $counts[$result['classification']]++;
                if ($result['candidate_normalized'] === null) {
                    continue;
                }
                // Cross-customer reuse is allowed for customers/users. User tenant duplicates
                // are advisory; only patient tenant duplicates would conflict with a future claim.
                $scope = $table === 'customers' ? 'all-customers' : ($row['customer_id'] === null ? 'global' : 'customer:'.$row['customer_id']);
                $group = hash_hmac('sha256', json_encode([$table, $scope, $result['candidate_normalized']], JSON_THROW_ON_ERROR), $secret);
                $raw = hash_hmac('sha256', (string) $value, $secret);
                $groups[$group]['owners'][(string) $row['id']] = true;
                $groups[$group]['formats'][$raw] = true;
                $groups[$group]['classification'] = $result['classification'];
            }
            $duplicates = 0;
            $equivalent = 0;
            $affected = 0;
            $claimConflicts = 0;
            foreach ($groups as $group) {
                if (count($group['owners']) > 1) {
                    $duplicates++;
                    $affected += count($group['owners']);
                    if ($group['classification'] === 'rut_candidate_checksum_valid') {
                        $claimConflicts++;
                    }
                }
                if (count($group['formats']) > 1) {
                    $equivalent++;
                }
            }
            $report['sources'][$table] = ['rows' => $total, 'soft_deleted_rows' => $deleted, 'classifications' => $counts,
                'duplicate_candidate_groups' => $duplicates, 'rows_in_duplicate_groups' => $affected, 'equivalent_format_groups' => $equivalent,
                'potential_patient_claim_conflicts' => $table === 'patients' ? $claimConflicts : 0,
                'duplicates_are_advisory' => $table !== 'patients'];
        }

        return $report;
    }
}
