<?php
require __DIR__.'/../../vendor/autoload.php';
$app=require __DIR__.'/../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
[$script,$database,$userId,$versionId,$action,$directory]=$argv;
if (!preg_match('/^vita_billing_test_[a-f0-9]{12}$/',$database)) exit(2);
config(['database.connections.mysql.database'=>$database]); Illuminate\Support\Facades\DB::purge('mysql');
file_put_contents($directory.'/'.getmypid().'.ready','1');
$deadline=microtime(true)+15;
while(!file_exists($directory.'/go') && microtime(true)<$deadline) usleep(10000);
if(!file_exists($directory.'/go')) exit(3);
try {
    if($action==='publish') app(App\Services\Billing\PlanVersionService::class)->publish(App\Models\User::findOrFail($userId),App\Models\BillingPlanVersion::findOrFail($versionId));
    else Illuminate\Support\Facades\DB::table('billing_plan_prices')->where('billing_plan_version_id',$versionId)->where('interval','month')->update(['amount'=>67890]);
    echo json_encode(['ok'=>true]);
} catch(Throwable $e) { echo json_encode(['ok'=>false,'message'=>$e->getMessage()]); }
