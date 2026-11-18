<?php
namespace Tests\Feature;

use App\Models\{BillingFeature,Customer,CustomerSubscription,Role,User};
use App\Services\Billing\{BillingCapabilities,PlanCatalogService,PlanVersionService,SubscriptionService};
use Illuminate\Support\Facades\DB;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class CustomerSubscriptionConcurrencyTest extends TestCase
{
    public function test_two_mysql_connections_cannot_record_the_same_renewal_twice(): void
    {
        $original=config('database.connections.mysql.database');
        $database='vita_subscription_test_'.bin2hex(random_bytes(6));
        $directory=storage_path('framework/testing/'.$database);
        if(!is_dir($directory)) mkdir($directory,0700,true);
        DB::statement("CREATE DATABASE `$database`"); $processes=[];
        try {
            foreach(DB::select('SHOW FULL TABLES WHERE Table_type = ?',['BASE TABLE']) as $row) {
                $table=array_values((array)$row)[0];
                DB::statement("CREATE TABLE `$database`.`$table` LIKE `$original`.`$table`");
            }
            config(['database.connections.mysql.database'=>$database]); DB::purge('mysql');

            (require database_path('migrations/2026_11_04_000100_install_integrity_triggers.php'))->up();
            foreach(BillingCapabilities::FEATURES as $code=>$name) BillingFeature::create(compact('code','name'));
            $actor=User::create(['name'=>'Concurrent admin','email'=>'subscription@example.test','password'=>'test-password','rut'=>'test','phone'=>'123','status'=>1]);
            $role=Role::create(['name'=>'Super Administrador']); $actor->roles()->attach($role->id);
            $customer=Customer::create(['name'=>'Concurrency customer','legal_name'=>'Test','holder'=>'Holder','rut'=>'test','phone'=>'123','email'=>'customer@example.test']);
            $plan=app(PlanCatalogService::class)->save($actor,['code'=>'concurrency','name'=>'Concurrent plan','active'=>1,'position'=>1]);
            $versions=app(PlanVersionService::class); $v=$versions->create($actor,$plan);
            $versions->save($actor,$v,['included_branches'=>1,'trial_days'=>0,'grace_days'=>0,'terms'=>'Test conditions',
                'prices'=>['month'=>['enabled'=>1,'amount'=>10000,'additional_branch_amount'=>0],'year'=>['enabled'=>0]],
                'limits'=>['users'=>['scope'=>'customer','unlimited'=>1],'professionals'=>['scope'=>'customer','unlimited'=>1],'branches'=>['scope'=>'customer','unlimited'=>1]]]);
            $versions->publish($actor,$v);
            $s=app(SubscriptionService::class)->create($actor,['customer_id'=>$customer->id,'billing_plan_price_id'=>$v->prices()->first()->id,
                'branch_quantity'=>1,'starts_on'=>'2026-01-31','use_trial'=>0,'note'=>'Concurrency test']);
            for($i=0;$i<2;$i++) {
                $process=new Process([PHP_BINARY,base_path('tests/Support/subscription-payment-worker.php'),$database,(string)$actor->id,(string)$s->id,$directory]);
                $process->setTimeout(25); $process->start(); $processes[]=$process;
            }
            $deadline=microtime(true)+15;
            while(count(glob($directory.'/*.ready'))<2 && microtime(true)<$deadline) usleep(10000);
            $this->assertCount(2,glob($directory.'/*.ready')); file_put_contents($directory.'/go','1'); $results=[];
            foreach($processes as $process) {
                $process->wait(); $this->assertTrue($process->isSuccessful(),$process->getErrorOutput());
                $results[]=json_decode($process->getOutput(),true,512,JSON_THROW_ON_ERROR);
            }
            $this->assertCount(1,array_filter($results,fn($r)=>$r['ok']),json_encode($results));
            $this->assertSame(1,$s->payments()->count());
            $this->assertSame(2,$s->events()->count());
            $this->assertSame(2,(int)$s->fresh()->revision);
            $this->assertSame('2026-02-28',$s->fresh()->next_renewal_on->toDateString());
            $barrier=$directory.'/branches'; mkdir($barrier,0700); $processes=[];
            for($i=0;$i<2;$i++) {
                $process=new Process([PHP_BINARY,base_path('tests/Support/subscription-payment-worker.php'),$database,(string)$actor->id,(string)$s->id,$barrier,'branch']);
                $process->setTimeout(25); $process->start(); $processes[]=$process;
            }
            $deadline=microtime(true)+15;
            while(count(glob($barrier.'/*.ready'))<2 && microtime(true)<$deadline) usleep(10000);
            $this->assertCount(2,glob($barrier.'/*.ready')); file_put_contents($barrier.'/go','1'); $results=[];
            foreach($processes as $process) {
                $process->wait(); $this->assertTrue($process->isSuccessful(),$process->getErrorOutput());
                $results[]=json_decode($process->getOutput(),true,512,JSON_THROW_ON_ERROR);
            }
            $this->assertCount(1,array_filter($results,fn($r)=>$r['ok']),json_encode($results));
            $this->assertSame(1,$customer->branches()->where('status',true)->count());
            $barrier=$directory.'/flow-receipts'; mkdir($barrier,0700); $processes=[];
            for($i=0;$i<2;$i++) {
                $process=new Process([PHP_BINARY,base_path('tests/Support/subscription-payment-worker.php'),$database,(string)$actor->id,(string)$s->id,$barrier,'flow-receipt']);
                $process->setTimeout(25); $process->start(); $processes[]=$process;
            }
            $deadline=microtime(true)+15;
            while(count(glob($barrier.'/*.ready'))<2 && microtime(true)<$deadline) usleep(10000);
            $this->assertCount(2,glob($barrier.'/*.ready')); file_put_contents($barrier.'/go','1');
            foreach($processes as $process) {
                $process->wait(); $this->assertTrue($process->isSuccessful(),$process->getErrorOutput());
                $this->assertTrue(json_decode($process->getOutput(),true,512,JSON_THROW_ON_ERROR)['ok']);
            }
            $this->assertSame(1,DB::table('flow_billing_receipts')->where('customer_subscription_id',$s->id)->count());
            $this->assertSame(2,$s->payments()->count());
            $this->assertSame('2026-03-31',$s->fresh()->next_renewal_on->toDateString());
        } finally {
            foreach($processes as $process) if($process->isRunning()) $process->stop();
            DB::disconnect('mysql'); config(['database.connections.mysql.database'=>$original]); DB::purge('mysql');
            // Only the generated schema-only test database is removed. Tenant data is never copied.
            DB::statement("DROP DATABASE IF EXISTS `$database`");
        }
    }
}
