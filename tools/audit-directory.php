<?php
// Local-only, isolated MySQL benchmark. Never seeds or resets the configured database.
require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use Illuminate\Support\Facades\{DB, Artisan};
use Illuminate\Http\Request;

$connection = config('database.default');
$config = config("database.connections.$connection");
if (($config['driver'] ?? '') !== 'mysql' || !in_array($config['host'], ['127.0.0.1','localhost','::1'], true)) {
    throw new RuntimeException('This audit only supports local MySQL.');
}
$database = 'vita_directory_audit_'.date('Ymd_His');
if (DB::select('SELECT SCHEMA_NAME FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME = ?', [$database])) throw new RuntimeException('Audit database already exists.');
DB::statement("CREATE DATABASE `$database` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
config(["database.connections.$connection.database"=>$database,'session.driver'=>'array','cache.default'=>'array','queue.default'=>'sync','mail.default'=>'array','app.debug'=>false]);
DB::purge($connection);
if (DB::selectOne('SELECT DATABASE() AS db')->db !== $database) throw new RuntimeException('Isolation check failed.');
Artisan::call('migrate', ['--force'=>true]);
$label = 'final';
foreach ($argv as $arg) if (str_starts_with($arg,'--label=')) $label=substr($arg,8);
if (!preg_match('/\A[a-z0-9-]+\z/', $label)) throw new RuntimeException('Invalid output label');
$out = storage_path('app/directory-audit/'.$label);
if (!is_dir($out)) mkdir($out, 0777, true);
file_put_contents($out.'/database.txt', $database);
if (in_array('--schema-only',$argv,true)) {echo "Isolated schema ready: $database\n";exit;}
$results=['database'=>$database,'php'=>PHP_VERSION,'mysql'=>DB::selectOne('SELECT VERSION() AS v')->v,
    'method'=>'Sequential in-process HTTP kernel, warm application, 7 measured requests after one warmup; no network/TLS; synthetic data, no images; array sessions/cache; not a concurrent load test.',
    'runs'=>[]];
App\Models\VitaWebsiteSetting::create(['id'=>1,'content'=>['published'=>true]]);
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$catalog = array_keys(config('specialties.catalog'));
$password = password_hash('synthetic-only', PASSWORD_BCRYPT, ['cost'=>4]);
$branchId=0;$userId=0;$specialtyId=0;$previous=0;
foreach ([100,500,1000,5000] as $volume) {
    for ($start=$previous+1;$start<=$volume;$start+=100) {
        $rows=array_fill_keys(['customers','clinic_branches','users','roles','website_pages','customer_directory_profiles','specialties','clinic_branch_user','clinic_branch_user_role','specialty_user'], []);
        for($i=$start;$i<=min($start+99,$volume);$i++) {
            $rows['customers'][]=['id'=>$i,'name'=>'Clínica sintética '.$i,'legal_name'=>'PRIVATE LEGAL','holder'=>'PRIVATE HOLDER','rut'=>'synthetic-'.$i,'email'=>'private'.$i.'@example.test','phone'=>'000','domain'=>'audit-'.$i,'status'=>$i%10?1:0];
            $branches=[];
            for($b=0;$b<1+$i%3;$b++) {
                $branches[]=++$branchId;
                $rows['clinic_branches'][]=['id'=>$branchId,'customer_id'=>$i,'name'=>'Sede '.$b,'address'=>'Calle sintética 123','status'=>$b===2 && $i%7===0?0:1,'directory_publication_enabled'=>$b===0?1:0];
            }
            $specialties=[];
            for($s=0;$s<2+$i%6;$s++) {
                $specialties[]=++$specialtyId;
                $cat=$catalog[$s];
                $rows['specialties'][]=['id'=>$specialtyId,'customer_id'=>$i,'catalog_id'=>$cat,'name'=>config('specialties.catalog')[$cat],'status'=>1,'public_visible'=>$s===6?0:1];
            }
            $rows['roles'][]=['id'=>$i,'customer_id'=>$i,'name'=>'Doctor'];
            $author=null;
            for($u=0;$u<2+$i%9;$u++) {
                $uid=++$userId;$author??=$uid;$bid=$branches[$u%count($branches)];
                $rows['users'][]=['id'=>$uid,'customer_id'=>$i,'name'=>'PRIVATE PROFESSIONAL','email'=>'provider'.$uid.'@example.test','password'=>$password,'status'=>$u===7?0:1];
                $rows['clinic_branch_user'][]=['user_id'=>$uid,'clinic_branch_id'=>$bid,'status'=>1];
                $rows['clinic_branch_user_role'][]=['customer_id'=>$i,'user_id'=>$uid,'clinic_branch_id'=>$bid,'role_id'=>$i];
                foreach (array_unique([$specialties[$u%count($specialties)],$specialties[($u+1)%count($specialties)]]) as $sid)
                    $rows['specialty_user'][]=['customer_id'=>$i,'clinic_branch_id'=>$bid,'user_id'=>$uid,'specialty_id'=>$sid];
            }
            $rows['website_pages'][]=['customer_id'=>$i,'title'=>'Inicio','slug'=>'inicio','status'=>1];
            $rows['customer_directory_profiles'][]=['customer_id'=>$i,'slug'=>'clinica-'.$i,'description'=>'Presentación sintética de atención en salud.','city'=>$i%2?'Quillota':'Santiago','region'=>'Región sintética','publication_enabled'=>$i%11?1:0,'authorized_by'=>$author,'authorized_at'=>now(),'authorization_version'=>'directory-v1','latitude'=>-32.8-$i%100/100,'longitude'=>-71.2,'show_public_contact'=>false,'show_public_social_links'=>false];
        }
        DB::transaction(function()use($rows){foreach($rows as $table=>$entries)foreach(array_chunk($entries,200) as $chunk)DB::table($table)->insert($chunk);});
    }
    $previous=$volume;
    unset($rows);
    echo "Seeded $volume clinics, $userId providers, $branchId branches\n";
    $eligible=app(App\Services\CustomerDirectoryService::class)->eligibleQuery();
    app(App\Services\CustomerDirectoryService::class)->filterBySpecialty($eligible,$catalog[0]);
    $total=$eligible->count();
    $cases=['initial'=>'/','specialty'=>'/?especialidad='.$catalog[0],
        'combined'=>'/?buscar=Quillota&especialidad='.$catalog[0],
        'nearby'=>'/?cercanas=1&especialidad='.$catalog[0],
        'detail'=>'/clinicas/clinica-1?especialidad='.$catalog[0],
        'page2'=>'/?especialidad='.$catalog[0].'&page=2',
        'lastpage'=>'/?especialidad='.$catalog[0].'&page='.max(1,(int)ceil($total/12))];
    foreach($cases as $case=>$path) {
        $samples=[];$queries=[];$html='';
        for($repeat=-1;$repeat<7;$repeat++) {
            app('session')->driver()->put('directory_location',['latitude'=>-32.88,'longitude'=>-71.25,'expires_at'=>time()+3600]);
            DB::enableQueryLog();DB::flushQueryLog();gc_collect_cycles();memory_reset_peak_usage();
            $before=memory_get_usage(true);$t=hrtime(true);
            $request=Request::create('http://'.config('app.domain').$path);
            $response=$kernel->handle($request);$html=$response->getContent();
            $ms=(hrtime(true)-$t)/1e6;
            $queries=DB::getQueryLog();DB::disableQueryLog();
            if($response->getStatusCode()!==200)throw new RuntimeException("HTTP {$response->getStatusCode()} at $case: ".strip_tags(substr($html,0,200)));
            if($repeat>=0)$samples[]=['ms'=>round($ms,2),'queries'=>count($queries),'sql_ms'=>round(array_sum(array_column($queries,'time')),2),'peak_mib'=>round(memory_get_peak_usage(true)/1048576,2),'extra_mib'=>round(max(0,memory_get_peak_usage(true)-$before)/1048576,2)];
            $kernel->terminate($request,$response);unset($response);
        }
        $times=array_column($samples,'ms');sort($times);
        $results['runs'][]=['clinics'=>$volume,'case'=>$case,'median_ms'=>$times[3],'p95_ms'=>$times[6],'samples'=>$samples,'cards'=>substr_count($html,'<article class="directory-card">')];
        file_put_contents($out.'/results.json',json_encode($results,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE));
        echo "$volume $case median={$times[3]}ms queries=".$samples[0]['queries']."\n";
        if($volume===5000) {
            file_put_contents($out.'/'.$case.'.html',$html);
            $plans=[];
            foreach($queries as $query)if(str_starts_with(strtolower($query['query']),'select')) {
                try {$plans[]=['sql'=>$query['query'],'plan'=>DB::select('EXPLAIN '.$query['query'],$query['bindings'])];}catch(Throwable $e){$plans[]=['error'=>$e->getMessage()];}
            }
            file_put_contents($out.'/explain-'.$case.'.json',json_encode($plans,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE));
        }
    }
}
echo "Results: $out/results.json; isolated database retained: $database\n";
