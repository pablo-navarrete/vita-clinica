<?php
require __DIR__.'/../../vendor/autoload.php';
$app=require __DIR__.'/../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
[$script,$customer,$intent,$session,$gate]=$argv;
if (getenv('VITA_IDENTIFIER_STORAGE_APPROVED')!=='yes' || !preg_match('/\Avita_identification_test_[a-z0-9_]+\z/',Illuminate\Support\Facades\DB::connection()->getDatabaseName())) exit(2);
config(['identification'=>Tests\Support\IdentifierStorageKeys::config()+['application_key'=>config('app.key')],
    'patient_registration.enabled'=>true,'patient_registration.channels.public_booking'=>true]);
$clinic=App\Models\Customer::findOrFail($customer);$app->instance('customer',$clinic);
Illuminate\Support\Facades\Mail::fake();Illuminate\Support\Facades\Notification::fake();
$app->instance(App\Support\ClinicAppointmentMessenger::class,Mockery::mock(App\Support\ClinicAppointmentMessenger::class)->shouldReceive('send')->andReturn([])->getMock());
session()->setId($session);session()->start();session()->put('portal_booking_grant',$intent);
$data=app(App\Services\MiSalud\PortalBookingService::class)->grant($intent,$session,(int)$customer)['selection'];
$request=Illuminate\Http\Request::create('/', 'POST', $data);$request->setLaravelSession(session()->driver());
touch($gate.'.ready');
try {
    app(App\Http\Controllers\customer\PublicBookingController::class)->store($request);
    echo Illuminate\Support\Facades\DB::table('portal_booking_authorizations')->where('id',$intent)->value('completed_at')?'completed':'unavailable';
} catch (Throwable) {echo 'failed';exit(1);}
