<?php

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
[$script, $database, $userId, $branchId, $catalogId, $barrier] = $argv;
if (!preg_match('/\Avita_specialty_test_[a-f0-9]{12}\z/', $database)) exit(2);
config(['database.connections.mysql.database' => $database]);
Illuminate\Support\Facades\DB::purge('mysql');
file_put_contents($barrier.'/'.getmypid().'.ready', '1');
$deadline = microtime(true) + 15;
while (!file_exists($barrier.'/go') && microtime(true) < $deadline) usleep(10000);
try {
    $user = App\Models\User::findOrFail($userId);
    app(App\Services\SpecialtyCatalog::class)->sync($user, $user->customer_id, (int)$branchId, [-(int)$catalogId]);
    echo 'ok';
} catch (Throwable $e) { fwrite(STDERR, $e->getMessage()); exit(1); }
