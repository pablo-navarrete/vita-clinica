<?php

namespace App\Services\Identification\Storage;

use RuntimeException;
use Throwable;

final class IdentifierCrypto
{
    private array $encryptionKeys;

    private array $lookupKeys;

    public readonly string $encryptionVersion;

    public readonly string $lookupVersion;

    public function __construct(array $config)
    {
        $this->encryptionVersion = $config['encryption_version'] ?? '';
        $this->lookupVersion = $config['lookup_version'] ?? '';
        $this->encryptionKeys = $this->decode($config['encryption_keys'] ?? []);
        $this->lookupKeys = $this->decode($config['lookup_keys'] ?? []);
        $applicationKey = $config['application_key'] ?? null;
        if (is_string($applicationKey) && $applicationKey !== '') {
            $applicationKey = str_starts_with($applicationKey, 'base64:') ? base64_decode(substr($applicationKey, 7), true) : $applicationKey;
            foreach (array_merge($this->encryptionKeys, $this->lookupKeys) as $key) {
                if (is_string($applicationKey) && hash_equals($applicationKey, $key)) {
                    throw new RuntimeException('Las claves de identificación deben ser independientes de APP_KEY.');
                }
            }
        }
        if (! isset($this->encryptionKeys[$this->encryptionVersion], $this->lookupKeys[$this->lookupVersion])) {
            throw new RuntimeException('Faltan claves activas de identificación. No se utiliza APP_KEY como alternativa.');
        }
        foreach ($this->encryptionKeys as $encryption) {
            foreach ($this->lookupKeys as $lookup) {
                if (hash_equals($encryption, $lookup)) {
                    throw new RuntimeException('Las claves de cifrado y búsqueda deben ser independientes.');
                }
            }
        }
    }

    public function encrypt(array $payload, array $binding): string
    {
        $iv = random_bytes(12);
        $tag = '';
        $version = $this->encryptionVersion;
        $plain = json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if (strlen($plain) > 524288) {
            throw new RuntimeException('El identificador supera el límite seguro de almacenamiento.');
        }
        $ciphertext = openssl_encrypt($plain, 'aes-256-gcm', $this->encryptionKeys[$version], OPENSSL_RAW_DATA,
            $iv, $tag, $this->json(['format' => 1, 'key_version' => $version, 'binding' => $binding]), 16);
        if ($ciphertext === false) {
            throw new RuntimeException('No se pudo cifrar el identificador.');
        }

        return $this->json(['format' => 1, 'key_version' => $version, 'iv' => base64_encode($iv), 'tag' => base64_encode($tag), 'ciphertext' => base64_encode($ciphertext)]);
    }

    public function decrypt(string $envelope, array $binding): array
    {
        try {
            if (strlen($envelope) > 1048576) {
                throw new RuntimeException;
            }
            $data = json_decode($envelope, true, 32, JSON_THROW_ON_ERROR);
            if (! is_array($data) || ($data['format'] ?? null) !== 1 || ! is_string($data['key_version'] ?? null)
                || ! isset($this->encryptionKeys[$data['key_version']])) {
                throw new RuntimeException;
            }
            $iv = base64_decode($data['iv'] ?? '', true);
            $tag = base64_decode($data['tag'] ?? '', true);
            $cipher = base64_decode($data['ciphertext'] ?? '', true);
            if ($iv === false || strlen($iv) !== 12 || $tag === false || strlen($tag) !== 16 || $cipher === false) {
                throw new RuntimeException;
            }
            $plain = openssl_decrypt($cipher, 'aes-256-gcm', $this->encryptionKeys[$data['key_version']], OPENSSL_RAW_DATA,
                $iv, $tag, $this->json(['format' => 1, 'key_version' => $data['key_version'], 'binding' => $binding]));
            if ($plain === false) {
                throw new RuntimeException;
            }
            $payload = json_decode($plain, true, 64, JSON_THROW_ON_ERROR);
            if (! is_array($payload)) {
                throw new RuntimeException;
            }

            return $payload;
        } catch (Throwable) {
            // Never include input, keys, OpenSSL details or previous exception payloads.
            throw new RuntimeException('No se pudo autenticar el identificador cifrado.');
        }
    }

    /** Version => digest; exact comparisons only. No raw number is stored in an index. */
    public function hashes(array $identityContext): array
    {
        $message = $this->json(['purpose' => 'vita-identification-lookup-v1', 'identity' => $identityContext]);
        $hashes = [];
        foreach ($this->lookupKeys as $version => $key) {
            $hashes[$version] = hash_hmac('sha256', $message, $key);
        }

        return $hashes;
    }

    private function decode(array $keys): array
    {
        $decoded = [];
        foreach ($keys as $version => $encoded) {
            if ($encoded === null || $encoded === '') {
                continue;
            }
            if (! preg_match('/\A[a-zA-Z0-9_-]{1,24}\z/D', (string) $version) || ! is_string($encoded) || ! str_starts_with($encoded, 'base64:')) {
                throw new RuntimeException('Configuración de claves de identificación no válida.');
            }
            $key = base64_decode(substr($encoded, 7), true);
            if ($key === false || strlen($key) !== 32) {
                throw new RuntimeException('Las claves de identificación deben tener 32 bytes.');
            }
            if (in_array($key, $decoded, true)) {
                throw new RuntimeException('Cada versión de clave debe tener material independiente.');
            }
            $decoded[(string) $version] = $key;
        }

        return $decoded;
    }

    private function json(array $value): string
    {
        $sort = function (array $data) use (&$sort): array {
            if (! array_is_list($data)) {
                ksort($data);
            }
            foreach ($data as &$item) {
                if (is_array($item)) {
                    $item = $sort($item);
                }
            }

            return $data;
        };

        return json_encode($sort($value), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}
