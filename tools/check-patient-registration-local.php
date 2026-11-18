<?php

// Operator smoke check through the real internal controller, with existing permissions.
require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\{Customer,User};
use App\Support\{ClinicBranchContext,MySqlRowFingerprint};
use Illuminate\Support\Facades\{DB,Auth};
use Illuminate\Http\Request;

try {
    $db=DB::connection();
    if(!$app->environment('local') || $db->getDatabaseName()!=='vita_clinica' || !in_array($db->getConfig('host'),['localhost','127.0.0.1','::1'],true)) throw new RuntimeException('Local development only.');
    if(($argv[1]??'')!=='--commit') throw new RuntimeException('Explicit --commit required for two synthetic local patients.');
    config(['session.driver'=>'array']);
    $actor=null; $branch=null; $clinic=null;
    foreach(Customer::where('status',1)->where('operating_country_code','CL')->get() as $candidate) {
        foreach(User::where('customer_id',$candidate->id)->where('status',1)->get() as $user) {
            foreach(ClinicBranchContext::availableBranches($user,$candidate->id) as $site) {
                if(ClinicBranchContext::hasPermissionInBranch($user,$candidate->id,$site->id,'patients.create') && ClinicBranchContext::hasPermissionInBranch($user,$candidate->id,$site->id,'patients.view')) {
                    $actor=$user; $branch=$site; $clinic=$candidate; break 3;
                }
            }
        }
    }
    if(!$actor) throw new RuntimeException('No existing authorized clinic user; no permissions will be changed.');
    $app->instance('customer',$clinic); Auth::setUser($actor);
    session(['active_branch_id'=>$branch->id,'active_branch_customer_id'=>$clinic->id]);
    $fingerprint=new MySqlRowFingerprint; $before=[];
    foreach($db->select("SHOW FULL TABLES WHERE Table_type='BASE TABLE'") as $table) {
        $name=array_values((array)$table)[0];
        $id=$db->selectOne("SELECT DATA_TYPE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND COLUMN_NAME='id'",[$name]);
        $max=$id && in_array($id->DATA_TYPE,['int','bigint','smallint','mediumint','tinyint'],true) ? ($db->table($name)->max('id')??0) : null;
        $before[$name]=['max'=>$max,'hash'=>$fingerprint->of($db->table($name)->cursor())];
    }
    $result=[]; $operations=[];
    DB::transaction(function() use($app,$db,$actor,$fingerprint,$before,&$result,&$operations) {
        $body=(string)random_int(50000000,59999999); $sum=0; $factor=2;
        foreach(array_reverse(str_split($body)) as $digit) { $sum+=(int)$digit*$factor; $factor=$factor===7?2:$factor+1; }
        $dv=11-($sum%11); $rut=$body.'-'.($dv===11?'0':($dv===10?'K':(string)$dv));
        foreach([['CL','national_id',$rut,'Chile'],['AR','passport','000VITA'.strtoupper(bin2hex(random_bytes(4))),'Extranjero']] as [$country,$type,$number,$label]) {
            $operation=(string)Illuminate\Support\Str::uuid(); $operations[]=$operation;
            $request=Request::create('/patient','POST',[
                'name'=>'Verificacion etapa 5 - '.$label,'birth_date'=>'1990-01-01','gender'=>'Otro',
                'phone'=>'900000000','email'=>'stage5-check@example.invalid','address'=>'Registro sintetico de verificacion local',
                'identification_country'=>$country,'identification_type'=>$type,'identification_number'=>$number,
                'registration_operation'=>$operation,
            ]);
            $request->setUserResolver(fn()=>$actor); $request->setLaravelSession(session()->driver());
            $app->instance('request',$request);
            $response=app(App\Http\Controllers\customer\PatientController::class)->store($request);
            $data=$response->getData(true); $id=$data['data']['id']??null;
            if($response->getStatusCode()!==200 || !$id) throw new RuntimeException('Internal controller check failed.');
            $patient=App\Models\Patient::findOrFail($id);
            if(!$patient->medicalRecord()->exists() || $patient->auditLogs()->count()!==2
                || $db->table('patient_identifiers')->where('patient_id',$id)->count()!==1
                || ($country==='CL' ? !$patient->rut : $patient->rut!==null)) throw new RuntimeException('Patient persistence check failed.');
            $service=app(App\Services\Patients\PatientRegistrationService::class);
            $document=$service->document($request->all());
            $match=app(App\Services\Patients\PatientIdentityResolver::class)->resolve($patient->customer_id,$document);
            if(($match['state']??null)!=='existing') throw new RuntimeException('Exact HMAC lookup failed.');
            $subject=new App\Services\Identification\Storage\IdentifierSubject('patient',$id,$patient->customer_id);
            $stored=app(App\Services\Identification\Storage\IdentifierRepositoryFactory::class)->make()->primary($subject,$actor);
            if(!$stored || $stored['number']!==$number || $stored['identity_verified']!==false) throw new RuntimeException('Encrypted identifier round trip failed.');
            $result[]=['patient_id'=>$id,'document_country'=>$country,'controller_status'=>200,'identifier_recorded'=>true,'encrypted_round_trip'=>true,'hmac_lookup'=>true,'medical_record_created'=>true,'audit_records'=>2];
        }
        foreach($before as $name=>$state) {
            $query=$db->table($name);
            if($state['max']!==null) $query->where('id','<=',$state['max']);
            elseif($name==='patient_registration_attempts') $query->whereNotIn('operation_id',$operations);
            if($fingerprint->of($query->cursor())!==$state['hash']) throw new RuntimeException('Existing row changed; smoke check rolled back.');
        }
    });
    $report=['database'=>'vita_clinica','manual_controller_smoke_check'=>true,'synthetic_patients'=>$result,'all_preexisting_rows_unchanged'=>true,
        'channels'=>config('patient_registration.channels'),'registration_enabled'=>config('patient_registration.enabled')];
    file_put_contents(base_path('docs/verification/patient-registration-stage5-local-check.json'),json_encode($report,JSON_PRETTY_PRINT));
    echo json_encode($report,JSON_PRETTY_PRINT).PHP_EOL;
} catch(Throwable $error) {
    // No documents, SQL bindings or secrets in the console.
    fwrite(STDERR,'Local check stopped ('.get_class($error).'). No automatic corrections.'.PHP_EOL); exit(1);
}
