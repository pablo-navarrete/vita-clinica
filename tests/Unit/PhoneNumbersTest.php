<?php

namespace Tests\Unit;

use App\Services\PhoneNumbers;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class PhoneNumbersTest extends TestCase
{
    #[DataProvider('numbers')]
    public function test_valid_numbers_are_normalized_and_country_is_derived_from_number(string $input, string $e164, string $country): void
    {
        $phones = new PhoneNumbers;
        $this->assertSame($e164, $phones->normalize($input));
        $this->assertSame($country, $phones->country($input));
    }

    public static function numbers(): array
    {
        return [
            ['+56 9 8765 4321','+56987654321','CL'], ['987654321','+56987654321','CL'],
            ['56987654321','+56987654321','CL'], ['0056 9 8765 4321','+56987654321','CL'],
            ['+54 9 11 2345-6789','+5491123456789','AR'], ['+51 987 654 321','+51987654321','PE'],
            ['+57 300 123 4567','+573001234567','CO'], ['+1 (202) 555-0123','+12025550123','US'],
            ['+1 416 555 0123','+14165550123','CA'], ['+34 612 345 678','+34612345678','ES'],
            ['+44 20 8366 1177','+442083661177','GB'], ['+55 11 91234-5678','+5511912345678','BR'],
            ['+56 2 2345 6789','+56223456789','CL'],
            ['352234567','+56352234567','CL'],
        ];
    }

    #[DataProvider('invalidNumbers')]
    public function test_rejects_invalid_country_codes_lengths_extensions_and_non_phone_content(mixed $input): void
    {
        $this->assertNull((new PhoneNumbers)->normalize($input));
    }

    public static function invalidNumbers(): array
    {
        return array_map(fn($input)=>[$input], ['',null,123,[], '123','911','+56','+999123456789',
            '+5691234567','+56 9 8765 4321 ext 4','+56 9 8765 4321abc','+56 9 8765 4321 / +56 9 8765 4321',str_repeat('1',90)]);
    }

    public function test_phone_fields_are_recognized_without_changing_other_fields_or_invalid_values(): void
    {
        $data=['phone'=>'9 8765 4321','admin_phone'=>'+1 416 555 0123','nested'=>['contact_phone'=>'0051987654321'],
            'reply_whatsapp'=>'56987654321','whatsapp_phone'=>'bad','phone_number_id'=>'123','name'=>'987654321'];
        $result=(new PhoneNumbers)->normalizeFields($data);
        $this->assertSame('+56987654321',$result['phone']);
        $this->assertSame('+14165550123',$result['admin_phone']);
        $this->assertSame('+51987654321',$result['nested']['contact_phone']);
        $this->assertSame('+56987654321',$result['reply_whatsapp']);
        $this->assertSame('bad',$result['whatsapp_phone']);
        $this->assertSame('123',$result['phone_number_id']);
        $this->assertSame('987654321',$result['name']);
    }
}
