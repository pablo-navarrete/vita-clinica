<?php
require __DIR__.'/../../vendor/autoload.php';
$app=require __DIR__.'/../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
if (getenv('VITA_IDENTIFIER_STORAGE_APPROVED')!=='yes' || !preg_match('/\Avita_identification_test_[a-z0-9_]+\z/',Illuminate\Support\Facades\DB::connection()->getDatabaseName())) exit(2);
config(['identification'=>Tests\Support\IdentifierStorageKeys::config()+['application_key'=>config('app.key')]]);
Illuminate\Support\Facades\Mail::fake();
try {
    app(App\Services\MiSalud\PortalAccountService::class)->register(['name'=>'Concurrent synthetic account',
        'email'=>getenv('VITA_LINK_TEST_EMAIL'),'password'=>'TestPassword123!','identification_country'=>'CL',
        'identification_type'=>'national_id','identification_number'=>getenv('VITA_LINK_TEST_RUT')]);
    app(App\Services\MiSalud\PortalPatientLinkService::class)->drain(1000);
    echo "ok\n";
} catch (Throwable) { fwrite(STDERR,"Synthetic link worker failed.\n"); exit(1); }
