<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'mailgun' => [
        'domain' => env('MAILGUN_DOMAIN'),
        'secret' => env('MAILGUN_SECRET'),
        'endpoint' => env('MAILGUN_ENDPOINT', 'api.mailgun.net'),
        'scheme' => 'https',
    ],

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'webrtc' => [
        'stun_urls' => array_values(array_filter(explode(',', env('WEBRTC_STUN_URLS', '')))),
        'turn_urls' => array_values(array_filter(explode(',', env('WEBRTC_TURN_URLS', '')))),
        'turn_secret' => env('WEBRTC_TURN_SECRET'),
    ],

    'whatsapp' => [
        'token' => env('WHATSAPP_CLOUD_TOKEN'),
        'phone_number_id' => env('WHATSAPP_PHONE_NUMBER_ID'),
        'graph_version' => env('WHATSAPP_GRAPH_VERSION'),
        'template_language' => env('WHATSAPP_TEMPLATE_LANGUAGE', 'es'),
        'templates' => [
            'created' => env('WHATSAPP_TEMPLATE_APPOINTMENT_CONFIRMATION'),
            'updated' => env('WHATSAPP_TEMPLATE_APPOINTMENT_UPDATE'),
            'cancelled' => env('WHATSAPP_TEMPLATE_APPOINTMENT_CANCELLED'),
            'confirmed' => env('WHATSAPP_TEMPLATE_APPOINTMENT_CONFIRMED'),
            'in_progress' => env('WHATSAPP_TEMPLATE_APPOINTMENT_IN_PROGRESS'),
            'attended' => env('WHATSAPP_TEMPLATE_APPOINTMENT_ATTENDED'),
            'telemedicine' => env('WHATSAPP_TEMPLATE_TELEMEDICINE_INVITATION'),
        ],
    ],

];
