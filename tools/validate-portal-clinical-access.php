<?php

// Tests run only on the dedicated, disposable database. Never copy application data here.
putenv('APP_ENV=testing'); putenv('DB_DATABASE=vita_testing');
$_ENV['APP_ENV']=$_SERVER['APP_ENV']='testing';
$_ENV['DB_DATABASE']=$_SERVER['DB_DATABASE']='vita_testing';
require __DIR__.'/../vendor/autoload.php';
$app=require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$connection=Illuminate\Support\Facades\DB::connection();
App\Support\TestingDatabaseGuard::assertIsolated($connection->getDriverName(),$connection->getDatabaseName());
if ($connection->getDatabaseName()!=='vita_testing') throw new RuntimeException('Dedicated test database required.');
$migration='database/migrations/2026_11_16_000100_create_portal_clinical_access.php';
if (Illuminate\Support\Facades\Artisan::call('migrate',['--path'=>$migration,'--force'=>true])!==0) throw new RuntimeException('Test migration failed.');
$tests=array_slice($argv,1) ?: ['tests/Feature/PortalClinicalHistoryTest.php'];
$process=new Symfony\Component\Process\Process(array_merge([PHP_BINARY,base_path('vendor/phpunit/phpunit/phpunit')],$tests,['--no-progress']),base_path(),['APP_ENV'=>'testing','DB_DATABASE'=>'vita_testing']);
$process->setTimeout(240);$process->run(fn($type,$output)=>print($output));exit($process->getExitCode());
