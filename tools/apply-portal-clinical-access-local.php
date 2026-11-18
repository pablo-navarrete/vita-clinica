<?php

// Local, additive deployment with a protected backup and a separate restore rehearsal.
require __DIR__.'/../vendor/autoload.php';
$app=require __DIR__.'/../bootstrap/app.php';$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use Illuminate\Support\Facades\{Artisan, DB};
use Symfony\Component\Process\Process;

function ensureClinical(bool $condition,string $message): void { if(!$condition)throw new RuntimeException($message); }
function privateClinical(array $command,?string $inputFile=null): void
{
    $process=new Process($command,base_path());$process->setTimeout(240);
    $input=$inputFile ? fopen($inputFile,'rb') : null;if($input)$process->setInput($input);
    $process->run();if($input)fclose($input);
    ensureClinical($process->isSuccessful(),'Private backup/restore operation failed. No application migration should be attempted.');
}
function clinicalSnapshot($connection): array
{
    $state=['tables'=>[],'triggers'=>[]];$database=$connection->getDatabaseName();
    foreach($connection->select("SHOW FULL TABLES WHERE Table_type='BASE TABLE'") as $entry){
        $name=array_values((array)$entry)[0];$quoted='`'.str_replace('`','``',$name).'`';
        $ddl=array_values((array)$connection->selectOne('SHOW CREATE TABLE '.$quoted))[1];
        // MySQL may make an inherited character set explicit after a dump restore.
        // Normalize only this equivalent spelling; preserve collations and counters.
        if(str_contains($ddl,'DEFAULT CHARSET=utf8mb4')){
            $ddl=preg_replace('/ CHARACTER SET utf8mb4(?= COLLATE utf8mb4_)/','',$ddl);
        }
        $checksum=$connection->selectOne('CHECKSUM TABLE '.$quoted)->Checksum;
        ensureClinical($checksum!==null,'Cannot verify table checksum.');
        $state['tables'][$name]=['rows'=>$connection->table($name)->count(),'checksum'=>$checksum,
            'ddl'=>hash('sha256',str_replace('`'.$database.'`.','`@self`.',$ddl))];
    }
    foreach($connection->select('SHOW TRIGGERS') as $trigger){
        $state['triggers'][$trigger->Trigger]=hash('sha256',str_replace('`'.$database.'`.','`@self`.',json_encode([
            $trigger->Event,$trigger->Table,$trigger->Statement,$trigger->Timing,$trigger->sql_mode])));
    }
    ksort($state['tables']);ksort($state['triggers']);return $state;
}

try {
    ensureClinical($app->environment('local'),'Local environment required.');
    $original=DB::getDefaultConnection();$configuration=config('database.connections.'.$original);$database=$configuration['database'];
    ensureClinical($configuration['driver']==='mysql' && in_array($configuration['host'],['localhost','127.0.0.1','::1'],true)
        && ($database==='vita_clinica' || preg_match('/\Avita_recovered_[0-9_]+\z/',$database)),'Unexpected application database.');
    $source=DB::connection();$before=clinicalSnapshot($source);
    ensureClinical(!isset($before['tables']['portal_clinical_grants']) && !isset($before['tables']['portal_clinical_events']),'Clinical access tables already exist; do not replay deployment.');
    new App\Services\Identification\Storage\IdentifierCrypto(config('identification'));
    $identity=new Process(['whoami','/user','/fo','csv','/nh']);$identity->mustRun();
    ensureClinical(preg_match('/S-1-5-[0-9-]+/',$identity->getOutput(),$sid)===1,'Cannot identify archive owner.');
    $stamp=gmdate('Ymd_His').'_'.bin2hex(random_bytes(3));
    $archive='C:/laragon/backups/vita-portal-clinical-access/'.$stamp;
    ensureClinical(mkdir($archive,0700,true),'Cannot create protected backup.');
    privateClinical(['icacls',$archive,'/inheritance:r','/grant:r','*'.$sid[0].':(OI)(CI)F','*S-1-5-18:(OI)(CI)F']);
    file_put_contents($archive.'/before.json',json_encode($before,JSON_PRETTY_PRINT));
    copy(base_path('.env'),$archive.'/application.env');
    $defaults=$archive.'/mysql-client.cnf';$bin='C:/laragon/bin/mysql/mysql-8.0.30-winx64/bin/';
    $escape=fn($value)=>'"'.str_replace(['\\','"',"\n","\r"],['\\\\','\\"','\\n','\\r'],(string)$value).'"';
    file_put_contents($defaults,"[client]\nhost=".$escape($configuration['host'])."\nport=".(int)$configuration['port']."\nuser=".$escape($configuration['username'])."\npassword=".$escape($configuration['password'])."\n");
    $target='vita_clinical_rehearsal_'.$stamp;$dump=$archive.'/database.sql';
    try {
        privateClinical([$bin.'mysqldump.exe','--defaults-extra-file='.$defaults,'--single-transaction','--source-data=2','--routines','--events','--triggers','--hex-blob','--set-gtid-purged=OFF','--no-tablespaces','--column-statistics=0','--result-file='.$dump,$database]);
        ensureClinical(filesize($dump)>0,'Empty backup.');
        ensureClinical(preg_match('/\Avita_clinical_rehearsal_[0-9_a-f]+\z/',$target)===1,'Unexpected rehearsal target.');
        $source->statement("CREATE DATABASE `$target` CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci");
        privateClinical([$bin.'mysql.exe','--defaults-extra-file='.$defaults,'--database='.$target],$dump);
    } finally { if(is_file($defaults))unlink($defaults); }
    $copyConfiguration=$configuration;$copyConfiguration['database']=$target;$copyConfiguration['url']=null;
    config(['database.connections.clinical_rehearsal'=>$copyConfiguration]);$copy=DB::connection('clinical_rehearsal');
    $restored=clinicalSnapshot($copy);
    if($restored!==$before){
        file_put_contents($archive.'/restored.json',json_encode($restored,JSON_PRETTY_PRINT));
        throw new RuntimeException('Restored backup differs from the captured application state.');
    }
    $migrationPath='database/migrations/2026_11_16_000100_create_portal_clinical_access.php';
    $migration=require base_path($migrationPath);
    try {
        DB::setDefaultConnection('clinical_rehearsal');$migration->up();
        $after=clinicalSnapshot($copy);
        foreach($before['tables'] as $name=>$table)ensureClinical(($after['tables'][$name]??null)===$table,'Rehearsal changed an existing table.');
        ensureClinical(isset($after['tables']['portal_clinical_grants'],$after['tables']['portal_clinical_events']),'Rehearsal tables missing.');
        $migration->down();ensureClinical(clinicalSnapshot($copy)===$before,'Rollback rehearsal differs from backup.');
    } finally { DB::setDefaultConnection($original); }
    ensureClinical(clinicalSnapshot($source)===$before,'Application changed during rehearsal; create a new backup before deployment.');
    $report=['database'=>$database,'backup'=>$dump,'backup_sha256'=>hash_file('sha256',$dump),'restore_database'=>$target,
        'restored_verified'=>true,'rehearsal_and_empty_rollback_verified'=>true,'applied'=>false];
    if(in_array('--apply',$argv,true)){
        ensureClinical(Artisan::call('migrate',['--database'=>$original,'--path'=>$migrationPath,'--force'=>true])===0,'Additive migration failed.');
        $after=clinicalSnapshot($source);
        foreach($before['tables'] as $name=>$table)if($name!=='migrations')ensureClinical(($after['tables'][$name]??null)===$table,'Existing table changed during deployment.');
        foreach($before['triggers'] as $name=>$hash)ensureClinical(($after['triggers'][$name]??null)===$hash,'Existing trigger changed.');
        $report['applied']=true;$report['existing_tables_and_triggers_unchanged']=true;
    }
    file_put_contents($archive.'/report.json',json_encode($report,JSON_PRETTY_PRINT));echo json_encode($report,JSON_PRETTY_PRINT),PHP_EOL;
} catch(Throwable $error){fwrite(STDERR,'Stopped: '.$error->getMessage().PHP_EOL);exit(1);}
