<?php

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
[$script, $database, $customer, $branch, $actor, $operation, $mode, $token, $gate] = $argv;
if (getenv('VITA_IDENTIFIER_STORAGE_APPROVED') !== 'yes' || !preg_match('/\Avita_identification_test_[a-z0-9_]+\z/', $database)
    || Illuminate\Support\Facades\DB::connection()->getDatabaseName() !== $database) exit(2);
config(['patient_registration.enabled' => true, 'patient_registration.channels' => ['internal'=>true,'viti'=>true,'public_booking'=>true], 'identification' => Tests\Support\IdentifierStorageKeys::config() + ['application_key' => config('app.key')]]);
$clinic = App\Models\Customer::findOrFail($customer); $app->instance('customer', $clinic);
session(['active_branch_customer_id' => (int) $customer, 'active_branch_id' => (int) $branch]);
Illuminate\Support\Facades\Mail::fake(); Illuminate\Support\Facades\Notification::fake();
$app->instance(App\Support\ClinicAppointmentMessenger::class, \Mockery::mock(App\Support\ClinicAppointmentMessenger::class)->shouldReceive('send')->andReturn([])->getMock());
touch($gate.'.ready');
try {
    if ($mode === 'public_booking') {
        $request = Illuminate\Http\Request::create('/', 'POST', ['registration_token' => $token]);
        app(App\Http\Controllers\customer\PublicBookingController::class)->verifyRegistration($request, $clinic->domain, $operation);
        echo Illuminate\Support\Facades\DB::table('patient_registration_attempts')->where('operation_id', $operation)->value('status');
    } else {
        $user = App\Models\User::findOrFail($actor); auth()->login($user);
        app(App\Services\Patients\PatientRegistrationService::class)->register($user, [
            'name' => 'Concurrent registration', 'gender' => 'Femenino', 'birth_date' => '1990-01-01', 'phone' => '123',
            'email' => 'patient@example.test', 'address' => 'Chile', 'identification_country' => 'AR',
            'identification_type' => 'passport', 'identification_number' => '000CONCURRENT',
        ], $mode, $operation);
        echo 'completed';
    }
} catch (Illuminate\Validation\ValidationException) { echo 'conflict'; }
catch (Throwable) { echo 'failed'; exit(1); } // Never print SQL, document numbers or bearer tokens.
