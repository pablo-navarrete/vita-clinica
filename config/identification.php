<?php

return [
    // Used only to reject accidental reuse, never as an encryption/search key.
    'application_key' => env('APP_KEY'),
    // Deliberately independent from APP_KEY; missing keys fail closed on explicit use.
    'encryption_version' => env('IDENTIFICATION_ENCRYPTION_VERSION', 'v1'),
    'encryption_keys' => [
        'v1' => env('IDENTIFICATION_ENCRYPTION_KEY_V1'),
        'v2' => env('IDENTIFICATION_ENCRYPTION_KEY_V2'),
    ],
    'lookup_version' => env('IDENTIFICATION_LOOKUP_VERSION', 'v1'),
    'lookup_keys' => [
        'v1' => env('IDENTIFICATION_LOOKUP_KEY_V1'),
        'v2' => env('IDENTIFICATION_LOOKUP_KEY_V2'),
    ],
];
