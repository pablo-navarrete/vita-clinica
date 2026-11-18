<?php

namespace Tests\Unit\Identification;

use App\Services\Identification\IdentificationService;
use App\Services\Identification\Storage\IdentifierCrypto;
use App\Services\Identification\Storage\IdentifierSubject;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class IdentifierCryptoTest extends TestCase
{
    public static function keys(string $version = 'v1'): array
    {
        return \Tests\Support\IdentifierStorageKeys::config($version);
    }

    public function test_authenticated_encryption_is_randomized_and_hides_all_document_values(): void
    {
        $crypto = new IdentifierCrypto(self::keys());
        $binding = ['owner' => 17, 'tenant' => 1, 'revision' => 1];
        $payload = ['original' => '12.345.678-5', 'normalized' => '123456785', 'audit' => [['action' => 'created']]];
        $one = $crypto->encrypt($payload, $binding);
        $two = $crypto->encrypt($payload, $binding);
        $this->assertNotSame($one, $two);
        $this->assertStringNotContainsString('12.345.678-5', $one);
        $this->assertStringNotContainsString('123456785', $one);
        $this->assertSame($payload, $crypto->decrypt($one, $binding));
    }

    public function test_ciphertext_tag_nonce_version_and_owner_binding_are_authenticated(): void
    {
        $crypto = new IdentifierCrypto(self::keys());
        $binding = ['owner' => 17, 'tenant' => 1];
        $encrypted = $crypto->encrypt(['original' => 'private-document'], $binding);
        foreach (['iv', 'tag', 'ciphertext', 'key_version'] as $field) {
            $data = json_decode($encrypted, true);
            $data[$field] = $field === 'key_version' ? 'v2' : base64_encode(str_repeat('x', $field === 'iv' ? 12 : 16));
            try {
                $crypto->decrypt(json_encode($data), $binding);
                $this->fail('Tampering must fail');
            } catch (RuntimeException $e) {
                $this->assertStringNotContainsString('private-document', $e->getMessage());
            }
        }
        $this->expectException(RuntimeException::class);
        $crypto->decrypt($encrypted, ['owner' => 18, 'tenant' => 1]);
    }

    public function test_independent_hmac_is_exact_scoped_and_stable_across_equivalent_rut_formats(): void
    {
        $crypto = new IdentifierCrypto(self::keys());
        $service = new IdentificationService;
        $one = $service->identify('CL', 'national_id', '123456785');
        $two = $service->identify('CL', 'tax_id', '12.345.678-5');
        $scope = new IdentifierSubject('patient', 1, 10);
        $sameTenant = new IdentifierSubject('patient', 2, 10);
        $otherTenant = new IdentifierSubject('patient', 3, 11);
        $hash = $crypto->hashes($scope->lookupContext($one));
        $this->assertSame($hash, $crypto->hashes($sameTenant->lookupContext($two)));
        $this->assertNotSame($hash, $crypto->hashes($otherTenant->lookupContext($one)));
        $this->assertNotSame($hash['v1'], hash('sha256', '123456785'));
        $this->assertNotSame($hash['v1'], $hash['v2']);
        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/D', $hash['v1']);
        $this->assertNotSame($crypto->hashes((new IdentifierSubject('customer', 10, 10))->lookupContext($one)),
            $crypto->hashes((new IdentifierSubject('customer', 11, 11))->lookupContext($one)));
        $this->assertNotSame($crypto->hashes((new IdentifierSubject('user', 1, 10))->lookupContext($one)),
            $crypto->hashes((new IdentifierSubject('user', 2, 10))->lookupContext($one)));
    }

    public function test_hmac_distinguishes_countries_and_preserves_foreign_significant_characters(): void
    {
        $crypto = new IdentifierCrypto(self::keys());
        $service = new IdentificationService;
        $scope = new IdentifierSubject('patient', 1, 10);
        $digest = fn ($country, $number) => $crypto->hashes($scope->lookupContext($service->identify($country, 'national_id', $number)))['v1'];
        $this->assertNotSame($digest('AR', '00123'), $digest('PE', '00123'));
        $this->assertNotSame($digest('AR', '00123'), $digest('AR', '123'));
        $this->assertNotSame($digest('AR', 'Ab123'), $digest('AR', 'AB123'));
    }

    public function test_old_envelopes_remain_readable_during_explicit_key_rotation(): void
    {
        $old = new IdentifierCrypto(self::keys());
        $new = new IdentifierCrypto(self::keys('v2'));
        $payload = ['original' => '001Ab-12', 'normalized' => '001Ab-12'];
        $binding = ['record' => 'one'];
        $v1 = $old->encrypt($payload, $binding);
        $this->assertSame($payload, $new->decrypt($v1, $binding));
        $v2 = $new->encrypt($new->decrypt($v1, $binding), $binding);
        $this->assertSame('v2', json_decode($v2, true)['key_version']);
        $removed = self::keys('v2');
        unset($removed['encryption_keys']['v1'],$removed['lookup_keys']['v1']);
        $onlyNew = new IdentifierCrypto($removed);
        $this->assertSame($payload, $onlyNew->decrypt($v2, $binding));
        $this->expectException(RuntimeException::class);
        $onlyNew->decrypt($v1, $binding);
    }

    public function test_keys_are_required_and_cannot_reuse_encryption_material_for_hmac(): void
    {
        foreach ([[], ['encryption_version' => 'v1', 'lookup_version' => 'v1', 'encryption_keys' => ['v1' => 'base64:bad'], 'lookup_keys' => []],
            array_replace(self::keys(), ['lookup_keys' => ['v1' => self::keys()['encryption_keys']['v1']]])] as $config) {
            try {
                new IdentifierCrypto($config);
                $this->fail('Unsafe configuration must fail');
            } catch (RuntimeException $e) {
                $this->assertNotSame('', $e->getMessage());
            }
        }
    }

    public function test_global_user_scope_is_explicit_and_invalid_subjects_are_rejected(): void
    {
        $this->assertSame('global', (new IdentifierSubject('user', 1, null))->scopeKey());
        foreach ([['patient', 1, null], ['customer', 1, 2], ['user', 0, 1], ['anything', 1, 1]] as $args) {
            try {
                new IdentifierSubject(...$args);
                $this->fail('Invalid scope');
            } catch (\InvalidArgumentException) {
                $this->assertTrue(true);
            }
        }
    }

    public function test_application_key_cannot_be_reused_for_either_key_purpose(): void
    {
        foreach (['encryption_keys', 'lookup_keys'] as $purpose) {
            $config = self::keys();
            $config['application_key'] = $config[$purpose]['v1'];
            try {
                new IdentifierCrypto($config);
                $this->fail('APP_KEY reuse must fail closed.');
            } catch (RuntimeException $e) {
                $this->assertStringContainsString('APP_KEY', $e->getMessage());
                $this->assertStringNotContainsString($config['application_key'], $e->getMessage());
            }
        }
    }
}
