<?php

// Controlled local deployment: verified backup, isolated rehearsal, additive migration only.
require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Services\Identification\Storage\IdentifierCrypto;
use App\Support\MySqlMetadataComparator;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Process\Process;

function ensure(bool $condition, string $message): void
{
    if (! $condition) {
        throw new RuntimeException($message);
    }
}

function runPrivate(array $command, ?string $input = null): void
{
    $process = new Process($command, base_path());
    $process->setTimeout(180);
    if ($input !== null) {
        $process->setInput($input);
    }
    $process->run();
    // Do not emit process errors: connection tooling may include sensitive details.
    ensure($process->isSuccessful(), 'Private operation failed (exit '.$process->getExitCode().'); no migration should be attempted.');
}

function snapshot($connection, bool $schemaOnly = false): array
{
    $snapshot = ['tables' => [], 'triggers' => [], 'routines' => [], 'events' => [], 'views' => []];
    foreach ($connection->select("SHOW FULL TABLES WHERE Table_type = 'BASE TABLE'") as $row) {
        $name = array_values((array) $row)[0];
        $table = '`'.str_replace('`', '``', $name).'`';
        $ddl = array_values((array) $connection->selectOne('SHOW CREATE TABLE '.$table))[1];
        $schemaName = $connection->getDatabaseName();
        $queries = [
            'columns' => 'SELECT COLUMN_NAME,ORDINAL_POSITION,COLUMN_DEFAULT,IS_NULLABLE,DATA_TYPE,CHARACTER_MAXIMUM_LENGTH,CHARACTER_OCTET_LENGTH,NUMERIC_PRECISION,NUMERIC_SCALE,DATETIME_PRECISION,CHARACTER_SET_NAME,COLLATION_NAME,COLUMN_TYPE,COLUMN_KEY,EXTRA,COLUMN_COMMENT,GENERATION_EXPRESSION,SRS_ID FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=? AND TABLE_NAME=? ORDER BY ORDINAL_POSITION',
            'indexes' => 'SELECT NON_UNIQUE,INDEX_NAME,SEQ_IN_INDEX,COLUMN_NAME,COLLATION,SUB_PART,NULLABLE,INDEX_TYPE,COMMENT,INDEX_COMMENT,IS_VISIBLE,EXPRESSION FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=? AND TABLE_NAME=? ORDER BY INDEX_NAME,SEQ_IN_INDEX',
            'constraints' => 'SELECT CONSTRAINT_NAME,CONSTRAINT_TYPE,ENFORCED FROM information_schema.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA=? AND TABLE_NAME=? ORDER BY CONSTRAINT_NAME',
            'foreign_keys' => 'SELECT CONSTRAINT_NAME,UNIQUE_CONSTRAINT_NAME,MATCH_OPTION,UPDATE_RULE,DELETE_RULE,REFERENCED_TABLE_NAME FROM information_schema.REFERENTIAL_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=? AND TABLE_NAME=? ORDER BY CONSTRAINT_NAME',
            'key_columns' => "SELECT CONSTRAINT_NAME,COLUMN_NAME,ORDINAL_POSITION,POSITION_IN_UNIQUE_CONSTRAINT,IF(REFERENCED_TABLE_SCHEMA=?, '@self',REFERENCED_TABLE_SCHEMA) AS REFERENCED_TABLE_SCHEMA,REFERENCED_TABLE_NAME,REFERENCED_COLUMN_NAME FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA=? AND TABLE_NAME=? ORDER BY CONSTRAINT_NAME,ORDINAL_POSITION",
            'checks' => 'SELECT tc.CONSTRAINT_NAME,cc.CHECK_CLAUSE,tc.ENFORCED FROM information_schema.TABLE_CONSTRAINTS tc JOIN information_schema.CHECK_CONSTRAINTS cc ON cc.CONSTRAINT_SCHEMA=tc.CONSTRAINT_SCHEMA AND cc.CONSTRAINT_NAME=tc.CONSTRAINT_NAME WHERE tc.TABLE_SCHEMA=? AND tc.TABLE_NAME=? ORDER BY tc.CONSTRAINT_NAME',
            'table_options' => 'SELECT ENGINE,VERSION,ROW_FORMAT,TABLE_COLLATION,CREATE_OPTIONS,TABLE_COMMENT FROM information_schema.TABLES WHERE TABLE_SCHEMA=? AND TABLE_NAME=?',
        ];
        $metadata = [];
        foreach ($queries as $section => $query) {
            $parameters = $section === 'key_columns' ? [$schemaName, $schemaName, $name] : [$schemaName, $name];
            $metadata[$section] = array_map(fn ($row) => (array) $row, $connection->select($query, $parameters));
        }
        $snapshot['tables'][$name] = ['ddl' => (new MySqlMetadataComparator)->tableFingerprint($ddl, $metadata)];
        if (! $schemaOnly) {
            $checksum = $connection->selectOne('CHECKSUM TABLE '.$table)->Checksum;
            ensure($checksum !== null, 'A table cannot be checksummed; stop for review.');
            $snapshot['tables'][$name] += ['rows' => $connection->table($name)->count(), 'checksum' => $checksum];
        }
    }
    ksort($snapshot['tables']);
    foreach ($connection->select('SHOW TRIGGERS') as $row) {
        $snapshot['triggers'][$row->Trigger] = hash('sha256', json_encode([$row->Event, $row->Table, $row->Statement, $row->Timing, $row->sql_mode, $row->Definer, $row->character_set_client, $row->collation_connection, $row->{'Database Collation'}]));
    }
    ksort($snapshot['triggers']);
    foreach ($connection->select('SELECT ROUTINE_NAME, ROUTINE_TYPE FROM information_schema.ROUTINES WHERE ROUTINE_SCHEMA=? ORDER BY ROUTINE_TYPE,ROUTINE_NAME', [$connection->getDatabaseName()]) as $routine) {
        $type = $routine->ROUTINE_TYPE === 'PROCEDURE' ? 'PROCEDURE' : 'FUNCTION';
        $name = '`'.str_replace('`', '``', $routine->ROUTINE_NAME).'`';
        $snapshot['routines'][$type.':'.$routine->ROUTINE_NAME] = hash('sha256', json_encode($connection->selectOne('SHOW CREATE '.$type.' '.$name)));
    }
    foreach ($connection->select('SHOW EVENTS') as $event) {
        $name = '`'.str_replace('`', '``', $event->Name).'`';
        $snapshot['events'][$event->Name] = hash('sha256', json_encode($connection->selectOne('SHOW CREATE EVENT '.$name)));
    }
    foreach ($connection->select("SHOW FULL TABLES WHERE Table_type = 'VIEW'") as $view) {
        $name = array_values((array) $view)[0];
        $snapshot['views'][$name] = hash('sha256', json_encode($connection->selectOne('SHOW CREATE VIEW `'.str_replace('`', '``', $name).'`')));
    }

    return $snapshot;
}

function protect(string $path, string $sid, bool $directory): void
{
    $inherit = $directory ? '(OI)(CI)' : '';
    runPrivate(['icacls', $path, '/inheritance:r', '/grant:r', '*'.$sid.':'.$inherit.'F', '*S-1-5-18:'.$inherit.'F']);
}

try {
    ensure($app->environment('local'),'Local environment required.');
    $connection=config('database.connections.'.config('database.default'));
    ensure($connection['driver']==='mysql' && in_array($connection['host'],['localhost','127.0.0.1','::1'],true) && $connection['database']==='vita_clinica','Only local vita_clinica is authorized.');
    $db=DB::connection();
    $server=$db->selectOne('SELECT VERSION() AS version,@@datadir AS datadir');
    ensure(str_starts_with($server->version,'8.0.30') && str_starts_with(strtolower(str_replace('\\','/',$server->datadir)),'c:/laragon/'),'Unexpected MySQL server.');
    $target='2026_11_08_000300_create_portal_booking_authorizations';
    ensure(!$db->table('migrations')->where('migration',$target)->exists(),'Migration is already installed.');
    ensure(!\Illuminate\Support\Facades\Schema::hasTable('portal_booking_authorizations'),'Unexpected target objects exist.');
    new IdentifierCrypto(config('identification'));
    $before=snapshot($db);
    $identity=new Process(['whoami','/user','/fo','csv','/nh']);$identity->mustRun();
    ensure(preg_match('/S-1-5-[0-9-]+/',$identity->getOutput(),$match)===1,'Cannot identify local user.');
    $backup='C:/laragon/backups/vita-portal-booking/'.gmdate('Ymd_His').'_'.bin2hex(random_bytes(3));
    ensure(mkdir($backup,0700,true),'Cannot create backup directory.');protect($backup,$match[0],true);
    $defaults=$backup.'/mysql-client.cnf';
    $escape=fn($value)=>'"'.str_replace(['\\','"',"\n","\r"],['\\\\','\\"','\\n','\\r'],(string)$value).'"';
    file_put_contents($defaults,"[client]\nhost=".$escape($connection['host'])."\nport=".(int)$connection['port']."\nuser=".$escape($connection['username'])."\npassword=".$escape($connection['password'])."\n");
    $bin='C:/laragon/bin/mysql/mysql-8.0.30-winx64/bin/';$dump=$backup.'/database.sql';
    $restoreName='vita_identification_restore_booking_'.gmdate('Ymd_His').'_'.bin2hex(random_bytes(3));
    try {
        runPrivate([$bin.'mysqldump.exe','--defaults-extra-file='.$defaults,'--single-transaction','--routines','--events','--triggers','--hex-blob','--set-gtid-purged=OFF','--no-tablespaces','--column-statistics=0','--result-file='.$dump,'vita_clinica']);
        ensure(filesize($dump)>0,'Backup is empty.');
        $db->statement("CREATE DATABASE `$restoreName` CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci");
        $input=fopen($dump,'rb');$restore=new Process([$bin.'mysql.exe','--defaults-extra-file='.$defaults,'--database='.$restoreName]);$restore->setInput($input);$restore->setTimeout(180);$restore->run();fclose($input);
        ensure($restore->isSuccessful(),'Restore failed. Development not migrated.');
    } finally { if (is_file($defaults)) unlink($defaults); }
    $restoreConfig=$connection;$restoreConfig['database']=$restoreName;$restoreConfig['url']=null;
    config(['database.connections.portal_link_rehearsal'=>$restoreConfig]);
    $copy=DB::connection('portal_link_rehearsal');
    ensure(snapshot($copy)===$before,'Backup restoration differs. Stop.');
    ensure(snapshot($db)===$before,'Development changed during backup. Stop.');
    $migration=require base_path('database/migrations/'.$target.'.php');
    $original=config('database.default');
    DB::setDefaultConnection('portal_link_rehearsal');
    $migration->up();
    $rehearsed=snapshot($copy);
    foreach ($before['tables'] as $table=>$state) ensure($rehearsed['tables'][$table]===$state,'Rehearsal changed an existing table.');
    $migration->down();ensure(snapshot($copy)===$before,'Rollback rehearsal differs. Stop.');
    DB::setDefaultConnection($original);
    ensure(snapshot($db)===$before,'Development changed before migration. Stop.');
    ensure(Artisan::call('migrate',['--path'=>'database/migrations/'.$target.'.php','--force'=>true])===0,'Migration failed. Stop.');
    $after=snapshot($db);
    foreach ($before['tables'] as $table=>$state) if ($table!=='migrations') ensure($after['tables'][$table]===$state,'Existing table changed. Stop.');
    foreach ($before['triggers'] as $name=>$state) ensure(($after['triggers'][$name]??null)===$state,'Existing trigger changed.');
    $report=['database'=>'vita_clinica','mysql'=>$server->version,'backup'=>$dump,'backup_sha256'=>hash_file('sha256',$dump),
        'restore_database'=>$restoreName,'restored_verified'=>true,'rehearsal_and_empty_rollback_verified'=>true,
        'migration'=>$target,'existing_tables_unchanged'=>true,'existing_triggers_unchanged'=>true,
        'new_tables'=>array_values(array_diff(array_keys($after['tables']),array_keys($before['tables']))),
        'new_triggers'=>array_values(array_diff(array_keys($after['triggers']),array_keys($before['triggers'])))];
    file_put_contents($backup.'/report.json',json_encode($report,JSON_PRETTY_PRINT));
    echo json_encode($report,JSON_PRETTY_PRINT).PHP_EOL;
} catch (\Throwable $error) {
    fwrite(STDERR,'Stopped: '.$error->getMessage().PHP_EOL);exit(1);
}
