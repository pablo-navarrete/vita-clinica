<?php
namespace Tests\Feature;
use App\Models\{BillingPlan, BillingFeature, User, Role};
use App\Services\Billing\{PlanVersionService, PlanCatalogService, BillingCapabilities};
use Illuminate\Support\Facades\DB;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class BillingCatalogConcurrencyTest extends TestCase
{
    public function test_mysql_serializes_duplicate_publication_and_concurrent_price_write(): void
    {
        $original=config('database.connections.mysql.database');
        $database='vita_billing_test_'.bin2hex(random_bytes(6));
        $directory=storage_path('framework/testing/'.$database);
        if(!is_dir($directory)) mkdir($directory,0700,true);
        DB::statement("CREATE DATABASE `$database`");
        $processes=[];
        try {
            foreach(DB::select('SHOW FULL TABLES WHERE Table_type = ?',['BASE TABLE']) as $row) {
                $table=array_values((array)$row)[0];
                DB::statement("CREATE TABLE `$database`.`$table` LIKE `$original`.`$table`");
            }
            config(['database.connections.mysql.database'=>$database]); DB::purge('mysql');

            (require database_path('migrations/2026_11_04_000100_install_integrity_triggers.php'))->up();
            foreach(BillingCapabilities::FEATURES as $code=>$name) BillingFeature::create(compact('code','name'));
            $admin=User::create(['name'=>'Concurrent admin','email'=>'billing@example.test','password'=>'test-password','rut'=>'test','phone'=>'123','status'=>1]);
            $role=Role::create(['name'=>'Super Administrador']); $admin->roles()->attach($role->id);
            $plan=app(PlanCatalogService::class)->save($admin,['code'=>'concurrency','name'=>'Concurrency','active'=>1,'position'=>1]);
            $service=app(PlanVersionService::class);
            foreach(['publish','price'] as $competitor) {
                $version=$service->create($admin,$plan);
                $data=['included_branches'=>1,'trial_days'=>0,'grace_days'=>0,'terms'=>'Concurrent conditions',
                    'prices'=>['month'=>['enabled'=>1,'amount'=>12345,'additional_branch_amount'=>1000],'year'=>['enabled'=>0]],
                    'limits'=>['users'=>['scope'=>'customer','unlimited'=>1], 'professionals'=>['scope'=>'customer','unlimited'=>1], 'branches'=>['scope'=>'customer','unlimited'=>1]]];
                $service->save($admin,$version,$data);
                $barrier=$directory.'/'.$competitor; mkdir($barrier,0700); $processes=[];
                foreach(['publish',$competitor] as $action) {
                    $process=new Process([PHP_BINARY,base_path('tests/Support/billing-catalog-worker.php'),$database,(string)$admin->id,(string)$version->id,$action,$barrier]);
                    $process->setTimeout(25); $process->start(); $processes[]=$process;
                }
                $deadline=microtime(true)+15;
                while(count(glob($barrier.'/*.ready'))<2 && microtime(true)<$deadline) usleep(10000);
                $this->assertCount(2,glob($barrier.'/*.ready'));
                file_put_contents($barrier.'/go','1'); $results=[];
                foreach($processes as $process) { $process->wait(); $this->assertTrue($process->isSuccessful(),$process->getErrorOutput()); $results[]=json_decode($process->getOutput(),true,512,JSON_THROW_ON_ERROR); }
                $version->refresh(); $this->assertSame('published',$version->status);
                if($competitor==='publish') $this->assertSame(1,count(array_filter($results,fn($result)=>$result['ok'])),json_encode($results));
                else $this->assertTrue($results[0]['ok'],json_encode($results));
                $this->assertSame(1,DB::table('billing_catalog_events')->where('billing_plan_version_id',$version->id)->where('action','version_published')->count());
                $snapshot=json_decode($version->snapshot,true);
                $this->assertSame((int)$version->prices()->where('interval','month')->value('amount'),(int)$snapshot['prices'][0]['amount']);
                $this->assertSame(hash('sha256',$version->snapshot),$version->sha256);
            }
        } finally {
            foreach($processes as $process) if($process->isRunning()) $process->stop();
            DB::disconnect('mysql'); config(['database.connections.mysql.database'=>$original]); DB::purge('mysql');
            // Only this generated, empty-schema test database is removed; no tenant data was copied.
            DB::statement("DROP DATABASE IF EXISTS `$database`");
        }
    }
}
