<?php

// Approved local stage-4 nullable migration. No identifier or clinical data writes.
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
    ensure($app->environment('local') && ! $app->configurationIsCached(), 'Uncached local environment required.');
    $db = DB::connection();
    $connection = $db->getConfig();
    ensure($db->getDriverName() === 'mysql' && in_array($connection['host'], ['127.0.0.1', 'localhost', '::1'], true)
        && $db->getDatabaseName() === 'vita_clinica' && empty($connection['url']) && empty($connection['read']) && empty($connection['write']), 'Only the local vita_clinica connection is authorized.');
    $server = $db->selectOne('SELECT VERSION() AS version,@@datadir AS datadir');
    ensure($server->version === '8.0.30' && str_starts_with(strtolower(str_replace('\\', '/', $server->datadir)), 'c:/laragon/'), 'Unexpected MySQL server.');
    $column = (array) $db->selectOne("SELECT COLUMN_TYPE,IS_NULLABLE,COLUMN_DEFAULT,CHARACTER_SET_NAME,COLLATION_NAME,EXTRA FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='patients' AND COLUMN_NAME='rut'");
    ensure($column === ['COLUMN_TYPE' => 'varchar(255)', 'IS_NULLABLE' => 'NO', 'COLUMN_DEFAULT' => null,
        'CHARACTER_SET_NAME' => 'utf8mb4', 'COLLATION_NAME' => 'utf8mb4_unicode_ci', 'EXTRA' => ''], 'Unexpected patients.rut schema; stop.');
    $unique = $db->select("SELECT INDEX_NAME,GROUP_CONCAT(COLUMN_NAME ORDER BY SEQ_IN_INDEX) AS columns_list FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='patients' AND NON_UNIQUE=0 GROUP BY INDEX_NAME");
    ensure(count(array_filter($unique, fn ($index) => $index->columns_list === 'customer_id,rut')) === 1, 'Expected clinic/RUT unique index is missing or ambiguous.');
    $target = '2026_11_06_000200_allow_absent_legacy_patient_rut';
    $path = 'database/migrations/'.$target.'.php';
    $pending = array_values(array_diff(array_map(fn ($file) => basename($file, '.php'), glob(database_path('migrations/*.php'))), $db->table('migrations')->pluck('migration')->all()));
    ensure($pending === [$target], 'Unexpected pending migrations.');
    new IdentifierCrypto(config('identification'));
    $envHash = hash_file('sha256', base_path('.env'));
    $report = ['database' => 'vita_clinica', 'host' => $connection['host'], 'mysql' => $server->version, 'column_before' => $column, 'target' => $target];
    if (($argv[1] ?? '') !== '--apply') {
        echo json_encode($report + ['preflight_passed' => true], JSON_PRETTY_PRINT).PHP_EOL;
        exit;
    }
    $before = snapshot($db);
    $patientValues = (new \App\Support\MySqlRowFingerprint)->of($db->table('patients')->cursor());
    $identity = new Process(['whoami', '/user', '/fo', 'csv', '/nh']);
    $identity->mustRun();
    ensure(preg_match('/S-1-5-[0-9-]+/', $identity->getOutput(), $match) === 1, 'Cannot protect backup.');
    $backup = 'C:/laragon/backups/vita-identification-stage4/'.gmdate('Ymd_His').'_'.bin2hex(random_bytes(3));
    ensure(mkdir($backup, 0700, true), 'Cannot create backup directory.');
    protect($backup, $match[0], true);
    ensure(copy(base_path('.env'), $backup.'/.env.recovery'), 'Cannot preserve recovery configuration.');
    $escape = fn ($value) => '"'.str_replace(['\\', '"', "\n", "\r"], ['\\\\', '\\"', '\\n', '\\r'], (string) $value).'"';
    $defaults = $backup.'/mysql-client.cnf';
    file_put_contents($defaults, "[client]\nhost=".$escape($connection['host'])."\nport=".(int) $connection['port']."\nuser=".$escape($connection['username'])."\npassword=".$escape($connection['password'])."\n");
    $bin = 'C:/laragon/bin/mysql/mysql-8.0.30-winx64/bin/';
    $dump = $backup.'/database.sql';
    $restoreName = 'vita_identification_restore_stage4_'.gmdate('Ymd_His').'_'.bin2hex(random_bytes(3));
    try {
        runPrivate([$bin.'mysqldump.exe', '--defaults-extra-file='.$defaults, '--single-transaction', '--routines', '--events', '--triggers', '--hex-blob', '--set-gtid-purged=OFF', '--no-tablespaces', '--column-statistics=0', '--result-file='.$dump, 'vita_clinica']);
        ensure(is_file($dump) && filesize($dump) > 0, 'Empty backup.');
        $db->statement("CREATE DATABASE `$restoreName` CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci");
        $input = fopen($dump, 'rb');
        $restore = new Process([$bin.'mysql.exe', '--defaults-extra-file='.$defaults, '--database='.$restoreName], base_path());
        $restore->setInput($input);
        $restore->setTimeout(180);
        $restore->run();
        fclose($input);
        ensure($restore->isSuccessful(), 'Restoration failed; habitual DB unchanged.');
        $restoreConfig = $connection;
        $restoreConfig['database'] = $restoreName;
        config(['database.connections.nullable_restore' => $restoreConfig]);
        $reference = DB::connection('nullable_restore');
        ensure(snapshot($reference) === $before, 'Restored schema or data differs; stop.');
        $report += ['backup_directory' => $backup, 'backup_sha256' => hash_file('sha256', $dump), 'restored_and_verified' => true, 'restore_database' => $restoreName];
        // Validate the exact migration on the restored backup in a fresh isolated process first.
        $childEnv = ['DB_DATABASE' => $restoreName, 'DB_CONNECTION' => 'mysql', 'DATABASE_URL' => false,
            'DB_HOST' => $connection['host'], 'DB_PORT' => (string) $connection['port'], 'DB_USERNAME' => $connection['username'], 'DB_PASSWORD' => $connection['password']];
        $migration = new Process([PHP_BINARY, base_path('artisan'), 'migrate', '--path='.$path, '--force'], base_path(), $childEnv);
        $migration->setTimeout(180);
        $migration->run();
        ensure($migration->isSuccessful(), 'Restored migration failed; stop before development.');
        $expectedColumn = $column;
        $expectedColumn['IS_NULLABLE'] = 'YES';
        ensure((array) $reference->selectOne("SELECT COLUMN_TYPE,IS_NULLABLE,COLUMN_DEFAULT,CHARACTER_SET_NAME,COLLATION_NAME,EXTRA FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='patients' AND COLUMN_NAME='rut'") === $expectedColumn, 'Unexpected restored column properties.');
        $expected = snapshot($reference);
        foreach ($before['tables'] as $name => $state) {
            if ($name === 'migrations') continue;
            if ($name === 'patients') {
                // CHECKSUM TABLE includes NULL storage representation. Compare actual
                // bytes here because this one approved ALTER changes that representation.
                ensure((new \App\Support\MySqlRowFingerprint)->of($reference->table('patients')->cursor()) === $patientValues, 'Restored patient values changed.');
                continue;
            }
            ensure($expected['tables'][$name]['rows'] === $state['rows'] && $expected['tables'][$name]['checksum'] === $state['checksum'], 'Restored migration changed data.');
            if ($name !== 'patients') ensure($expected['tables'][$name] === $state, 'Unexpected restored schema change.');
        }
        ensure($expected['triggers'] === $before['triggers'], 'Unexpected trigger change.');
        ensure(snapshot($db) === $before && hash_file('sha256', base_path('.env')) === $envHash, 'Local activity/configuration changed; stop.');
        $exit = Artisan::call('migrate', ['--path' => $path, '--force' => true]);
        ensure($exit === 0, 'Local migration failed; no automatic rollback.');
        ensure(snapshot($db) === $expected, 'Local result differs from restored validation. Stop.');
        ensure(hash_file('sha256', base_path('.env')) === $envHash, 'Environment changed.');
        $report += ['migration_applied' => true, 'data_unchanged' => true, 'patient_values_byte_verified' => true, 'schema_matches_restored_validation' => true, 'triggers_unchanged' => true, 'env_unchanged' => true];
        file_put_contents($backup.'/application-report.json', json_encode($report, JSON_PRETTY_PRINT));
        echo json_encode($report, JSON_PRETTY_PRINT).PHP_EOL;
    } finally {
        if (is_file($defaults)) unlink($defaults);
    }
} catch (Throwable $error) {
    fwrite(STDERR, $error instanceof \Illuminate\Database\QueryException ? "Stopped: database operation failed; inspect privately.\n" : 'Stopped: '.$error->getMessage().PHP_EOL);
    exit(1);
}
