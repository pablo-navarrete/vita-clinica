<?php
return [
    'enabled'=>env('FLOW_ENABLED',false),
    'environment'=>env('FLOW_ENVIRONMENT','sandbox'),
    'api_key'=>env('FLOW_API_KEY'),
    'secret_key'=>env('FLOW_SECRET_KEY'),
    'alert_email'=>env('FLOW_ALERT_EMAIL'),
    'public_url'=>env('FLOW_PUBLIC_URL'),
    'production_payments'=>env('FLOW_PRODUCTION_PAYMENTS',false),
];
