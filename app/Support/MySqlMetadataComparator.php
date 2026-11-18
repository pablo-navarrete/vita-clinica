<?php

namespace App\Support;

/** Operational backup verifier. Never changes a database or interprets identifiers. */
final class MySqlMetadataComparator
{
    public function tableFingerprint(string $ddl, array $metadata): string
    {
        // Only the previously explained column qualifier may be redundant. Its effective
        // charset, collation, type, defaults and all other metadata remain in the hash.
        foreach ($metadata['columns'] as $column) {
            $charset = $column['CHARACTER_SET_NAME'];
            $collation = $column['COLLATION_NAME'];
            if ($charset !== 'utf8mb4' || $collation !== 'utf8mb4_unicode_ci') {
                continue;
            }
            $prefix = preg_quote('`'.str_replace('`', '``', $column['COLUMN_NAME']).'` '.$column['COLUMN_TYPE'], '~');
            $ddl = preg_replace_callback('~^(\s*'.$prefix.') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci(?=[ ,\r\n]|$)~m',
                fn ($match) => $match[1].' COLLATE utf8mb4_unicode_ci', $ddl);
        }
        // Reference schemas have synthetic rows and therefore different next IDs.
        // Restrict this existing exception to the table options, never column defaults.
        $ddl = preg_replace('~^(\) ENGINE=[^\r\n]*?) AUTO_INCREMENT=\d+~m', '$1', $ddl);

        return hash('sha256', json_encode(['ddl' => $ddl, 'metadata' => $metadata], JSON_THROW_ON_ERROR));
    }
}
