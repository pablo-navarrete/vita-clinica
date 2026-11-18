<?php
require __DIR__.'/../../vendor/autoload.php';
$app=require __DIR__.'/../../bootstrap/app.php';$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
if(getenv('VITA_IDENTIFIER_STORAGE_APPROVED')!=='yes'||!preg_match('/\Avita_identification_test_[a-z0-9_]+\z/',Illuminate\Support\Facades\DB::connection()->getDatabaseName()))exit(2);
[$script,$clinicId,$branchId,$actorId,$professional,$patient,$specialty,$date]=$argv;
$app->instance('customer',App\Models\Customer::findOrFail($clinicId));$user=App\Models\User::findOrFail($actorId);auth()->login($user);
session(['active_branch_customer_id'=>(int)$clinicId,'active_branch_id'=>(int)$branchId]);
Illuminate\Support\Facades\Notification::fake();Illuminate\Support\Facades\Mail::fake();
$app->instance(App\Support\ClinicAppointmentMessenger::class,\Mockery::mock(App\Support\ClinicAppointmentMessenger::class)->shouldReceive('send')->andReturn([])->getMock());
try{
 $request=Illuminate\Http\Request::create('/appointments','POST',['patient_id'=>$patient,'professional_id'=>$professional,'specialty_id'=>$specialty,'clinic_branch_id'=>$branchId,'date'=>$date,'time'=>'10:00','modality'=>'in_person']);
 $request->setUserResolver(fn()=>$user);
 $response=app(App\Http\Controllers\customer\AppointmentController::class)->store($request);
 echo $response->getStatusCode();
}catch(Throwable){fwrite(STDERR,"Isolated booking worker failed.\n");exit(1);}
