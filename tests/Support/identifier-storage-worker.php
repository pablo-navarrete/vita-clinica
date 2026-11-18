<?php

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
[$script,$database,$customer,$actor,$branch,$patient,$gate] = $argv;
if (getenv('VITA_IDENTIFIER_STORAGE_APPROVED') !== 'yes' || ! preg_match('/\Avita_identification_test_[a-z0-9_]+\z/', $database)) {
    exit(2);
}
config(['database.connections.mysql.database' => $database]);
\Illuminate\Support\Facades\DB::purge('mysql');
app()->instance('customer', \App\Models\Customer::findOrFail($customer));
session(['active_branch_customer_id' => (int) $customer, 'active_branch_id' => (int) $branch]);
$deadline = microtime(true) + 10;
touch($gate.'.'.$patient.'.ready');
while (! is_file($gate)) {
    if (microtime(true) > $deadline) {
        exit(3);
    }usleep(10000);
}
$repo = new \App\Services\Identification\Storage\IdentifierRepository(\Illuminate\Support\Facades\DB::connection(), new \App\Services\Identification\Storage\IdentifierCrypto(\Tests\Support\IdentifierStorageKeys::config()), new \App\Services\Identification\Storage\VitaIdentifierAuthorizer);
try {
    $repo->create(new \App\Services\Identification\Storage\IdentifierSubject('patient', (int) $patient, (int) $customer), \App\Models\User::findOrFail($actor), 'CL', 'national_id', '123456785');
    echo 'created';
} catch (\InvalidArgumentException) {
    echo 'duplicate';
}
