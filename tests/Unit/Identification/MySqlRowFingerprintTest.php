<?php

namespace Tests\Unit\Identification;

use App\Support\MySqlRowFingerprint;
use PHPUnit\Framework\TestCase;

class MySqlRowFingerprintTest extends TestCase
{
    public function test_column_and_row_order_do_not_change_values(): void
    {
        $check = new MySqlRowFingerprint;
        $this->assertSame($check->of([['id' => 1, 'rut' => '0001'], ['id' => 2, 'rut' => null]]),
            $check->of([(object) ['rut' => null, 'id' => 2], (object) ['rut' => '0001', 'id' => 1]]));
    }

    public static function differences(): array
    {
        return [[null, ''], ['0001', '1'], ["\xff", "\xfe"], ['a', 'A'], [1, '1']];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('differences')]
    public function test_rejects_real_value_or_type_differences(mixed $before, mixed $after): void
    {
        $check = new MySqlRowFingerprint;
        $this->assertNotSame($check->of([['value' => $before]]), $check->of([['value' => $after]]));
    }

    public function test_duplicate_rows_are_not_lost(): void
    {
        $check = new MySqlRowFingerprint;
        $this->assertNotSame($check->of([['value' => 'same']]), $check->of([['value' => 'same'], ['value' => 'same']]));
    }
}
