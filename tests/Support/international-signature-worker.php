<?php

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
[$script, $database, $customer, $doctor, $admin, $branch, $patient, $encounter, $operation, $mode, $gate] = $argv;
if (getenv('VITA_IDENTIFIER_STORAGE_APPROVED') !== 'yes'
    || ! preg_match('/\Avita_identification_test_[a-z0-9_]+\z/', $database)
    || \Illuminate\Support\Facades\DB::connection()->getDatabaseName() !== $database) exit(2);
config(['identification' => Tests\Support\IdentifierStorageKeys::config() + ['application_key' => config('app.key')]]);
$app->instance('customer', App\Models\Customer::findOrFail($customer));
session(['active_branch_customer_id' => (int) $customer, 'active_branch_id' => (int) $branch]);
$user = App\Models\User::findOrFail($doctor);
auth()->login($user);
touch($gate.'.ready');
try {
    if (in_array($mode, ['sign', 'patient-sign'], true)) {
        app(App\Services\Signatures\EncounterSigningService::class)->sign($user, (int) $patient, (int) $encounter, $operation, 'correct-password', true);
        echo 'signed';
    } elseif ($mode === 'edit') {
        app(App\Services\Identification\Storage\IdentifierRepositoryFactory::class)->make()->create(
            new App\Services\Identification\Storage\IdentifierSubject('user', (int) $doctor, (int) $customer),
            App\Models\User::findOrFail($admin), 'ES', 'passport', '000AFTER', 'natural_person', true);
        echo 'edited';
    } elseif ($mode === 'patient-edit') {
        // Same tenant root and patient lock order as the patient update endpoint.
        Illuminate\Support\Facades\DB::transaction(function () use ($customer, $patient) {
            Illuminate\Support\Facades\DB::table('customers')->where('id', $customer)->lockForUpdate()->first();
            $row = App\Models\Patient::where('customer_id', $customer)->lockForUpdate()->findOrFail($patient);
            $row->update(['name' => 'Concurrent patient correction']);
        });
        echo 'edited';
    } else exit(3);
} catch (Illuminate\Validation\ValidationException) {
    echo 'stale';
} catch (Throwable) {
    // Never print SQL, documents or authentication evidence.
    echo 'failed';
    exit(1);
}
