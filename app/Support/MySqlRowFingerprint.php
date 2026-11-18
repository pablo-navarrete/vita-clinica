<?php

namespace App\Support;

/** Byte-safe multiset of fetched values, independent of InnoDB's NULL bitmap. */
final class MySqlRowFingerprint
{
    public function of(iterable $rows): array
    {
        $hashes = [];
        foreach ($rows as $row) {
            $values = [];
            foreach ((array) $row as $column => $value) {
                $values[$column] = $value === null ? ['null'] : [get_debug_type($value), base64_encode((string) $value)];
            }
            ksort($values, SORT_STRING);
            $hashes[] = hash('sha256', json_encode($values, JSON_THROW_ON_ERROR));
        }
        sort($hashes, SORT_STRING);

        return ['rows' => count($hashes), 'sha256' => hash('sha256', implode('', $hashes))];
    }
}
