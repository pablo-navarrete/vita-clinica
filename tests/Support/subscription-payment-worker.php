<?php
require __DIR__.'/../../vendor/autoload.php';
$app=require __DIR__.'/../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
[$script,$database,$actor,$subscription,$barrier]=$argv;
if(!preg_match('/^vita_subscription_test_[a-f0-9]{12}$/',$database)) exit(2);
config(['database.connections.mysql.database'=>$database]);
Illuminate\Support\Facades\DB::purge('mysql');
file_put_contents($barrier.'/'.getmypid().'.ready','1');
$deadline=microtime(true)+15;
while(!is_file($barrier.'/go') && microtime(true)<$deadline) usleep(10000);
try {
    $s=App\Models\CustomerSubscription::findOrFail($subscription);
    if (($argv[5] ?? '') === 'flow-receipt') {
        config(['flow.environment'=>'sandbox','flow.api_key'=>'concurrency-fixture-key']);
        $quote=app(App\Services\Billing\SubscriptionService::class)->upcoming($s);
        $payment=['flowOrder'=>900000,'commerceOrder'=>'concurrent-confirmed-payment','currency'=>'CLP','amount'=>$quote['amount'],'status'=>2,
            'paymentData'=>['date'=>$quote['period_start'].' 12:00:00','currency'=>'CLP','amount'=>$quote['amount']]];
        app(App\Services\Billing\ClinicBillingService::class)->settle($s,$quote,$payment,(int)$actor,'sandbox',hash('sha256','concurrency-fixture-key'));
    } elseif (($argv[5] ?? '') === 'branch') {
        app(App\Services\Billing\BranchCapacityService::class)->create($s->customer,['name'=>'Concurrent branch '.getmypid()]);
    } else {
        app(App\Services\Billing\SubscriptionService::class)->recordPayment(App\Models\User::findOrFail($actor),$s,
            ['revision'=>1,'paid_on'=>'2026-01-31','amount'=>10000,'reference'=>'same-payment','note'=>'Concurrency test']);
    }
    echo json_encode(['ok'=>true]);
} catch(Illuminate\Validation\ValidationException $e) {
    echo json_encode(['ok'=>false,'errors'=>$e->errors()]);
}
