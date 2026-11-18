<?php

namespace Tests\Support;

final class IdentifierStorageKeys
{
    // Public, deterministic test material only. Never use these values in .env.
    public static function config(string $version = 'v1'): array
    {
        return ['encryption_version' => $version, 'lookup_version' => $version,
            'encryption_keys' => ['v1' => 'base64:'.base64_encode(str_repeat('e', 32)), 'v2' => 'base64:'.base64_encode(str_repeat('E', 32))],
            'lookup_keys' => ['v1' => 'base64:'.base64_encode(str_repeat('h', 32)), 'v2' => 'base64:'.base64_encode(str_repeat('H', 32))]];
    }
}
