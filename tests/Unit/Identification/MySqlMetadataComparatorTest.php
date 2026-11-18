<?php

namespace Tests\Unit\Identification;

use App\Support\MySqlMetadataComparator;
use PHPUnit\Framework\TestCase;

class MySqlMetadataComparatorTest extends TestCase
{
    private function metadata(): array
    {
        return ['columns' => [['COLUMN_NAME' => 'document', 'COLUMN_TYPE' => 'varchar(255)', 'CHARACTER_SET_NAME' => 'utf8mb4', 'COLLATION_NAME' => 'utf8mb4_unicode_ci', 'IS_NULLABLE' => 'NO', 'COLUMN_DEFAULT' => null]],
            'indexes' => [['INDEX_NAME' => 'document_unique', 'NON_UNIQUE' => 0]],
            'foreign_keys' => [['DELETE_RULE' => 'RESTRICT']], 'checks' => [['CHECK_CLAUSE' => 'length(document) > 0', 'ENFORCED' => 'YES']]];
    }

    private function ddl(bool $explicit = false): string
    {
        return "CREATE TABLE `example` (\n  `document` varchar(255) ".($explicit ? 'CHARACTER SET utf8mb4 ' : '')."COLLATE utf8mb4_unicode_ci NOT NULL,\n  CHECK (length(document) > 0)\n) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    }

    public function test_accepts_only_explained_redundant_charset_with_equal_effective_metadata(): void
    {
        $comparator = new MySqlMetadataComparator;
        $this->assertSame($comparator->tableFingerprint($this->ddl(), $this->metadata()), $comparator->tableFingerprint($this->ddl(true), $this->metadata()));
    }

    public function test_rejects_effective_metadata_changes_even_when_ddl_text_appears_equal(): void
    {
        $comparator = new MySqlMetadataComparator;
        $metadata = $this->metadata();
        $before = $comparator->tableFingerprint($this->ddl(), $metadata);
        foreach (['COLUMN_TYPE' => 'varchar(128)', 'CHARACTER_SET_NAME' => 'latin1', 'COLLATION_NAME' => 'utf8mb4_0900_ai_ci', 'IS_NULLABLE' => 'YES', 'COLUMN_DEFAULT' => 'different'] as $field => $value) {
            $changed = $metadata;
            $changed['columns'][0][$field] = $value;
            $this->assertNotSame($before, $comparator->tableFingerprint($this->ddl(true), $changed));
        }
        foreach (['indexes', 'foreign_keys', 'checks'] as $section) {
            $changed = $metadata;
            $changed[$section] = [];
            $this->assertNotSame($before, $comparator->tableFingerprint($this->ddl(true), $changed));
        }
    }

    public function test_equivalent_nullable_text_column_with_comma_after_collation(): void
    {
        $metadata = $this->metadata();
        $metadata['columns'][0]['COLUMN_TYPE'] = 'text';
        $metadata['columns'][0]['IS_NULLABLE'] = 'YES';
        $plain = "CREATE TABLE `example` (\n  `document` text COLLATE utf8mb4_unicode_ci,\n  `id` int\n) ENGINE=InnoDB";
        $explicit = str_replace('text COLLATE', 'text CHARACTER SET utf8mb4 COLLATE', $plain);
        $comparator = new MySqlMetadataComparator;
        $this->assertSame($comparator->tableFingerprint($plain, $metadata), $comparator->tableFingerprint($explicit, $metadata));
    }

    public function test_rejects_unexplained_ddl_and_preserves_literals_that_look_like_charset_qualifiers(): void
    {
        $comparator = new MySqlMetadataComparator;
        $ddl = $this->ddl();
        $metadata = $this->metadata();
        foreach (['NOT NULL' => 'NULL', 'CHECK (length(document) > 0)' => 'CHECK (length(document) > 1)', 'ENGINE=InnoDB' => 'ENGINE=MyISAM'] as $from => $to) {
            $this->assertNotSame($comparator->tableFingerprint($ddl, $metadata), $comparator->tableFingerprint(str_replace($from, $to, $ddl), $metadata));
        }
        $literal = " DEFAULT ' CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci literal'";
        $this->assertNotSame($comparator->tableFingerprint($ddl.$literal, $metadata), $comparator->tableFingerprint($ddl.str_replace(' CHARACTER SET utf8mb4', '', $literal), $metadata));
        $this->assertNotSame($comparator->tableFingerprint($ddl." DEFAULT 'AUTO_INCREMENT=2'", $metadata), $comparator->tableFingerprint($ddl." DEFAULT 'AUTO_INCREMENT=3'", $metadata));
    }
}
