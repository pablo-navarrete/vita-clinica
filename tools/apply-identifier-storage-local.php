<?php

// Explicit local-development operation. No historical import and no current flow integration.
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
    ensure($app->environment('local'), 'APP_ENV is not local. Stop.');
    ensure(! $app->configurationIsCached(), 'Cached configuration detected. Stop for review.');
    $db = DB::connection();
    $connection = $db->getConfig();
    ensure($db->getDriverName() === 'mysql' && in_array($connection['host'], ['127.0.0.1', 'localhost', '::1'], true), 'Connection is not exclusively local MySQL.');
    ensure(empty($connection['url']) && empty($connection['read']) && empty($connection['write']), 'URL or routed DB connection detected. Stop.');
    $database = $db->getDatabaseName();
    ensure($database === 'vita_clinica', 'Only the explicitly approved vita_clinica schema is allowed.');
    ensure(preg_match('/\A[a-zA-Z0-9_]+\z/', $database) === 1 && ! preg_match('/prod|production|test/i', $database), 'Unexpected development database name.');
    ensure(str_starts_with(strtolower(str_replace('\\', '/', realpath(base_path()))), 'c:/laragon/www/'), 'Not a local Laragon workspace.');
    $server = $db->selectOne('SELECT VERSION() AS version, @@hostname AS hostname, @@port AS port, @@datadir AS datadir, DATABASE() AS selected_db');
    ensure($server->version === '8.0.30' && str_starts_with(strtolower(str_replace('\\', '/', $server->datadir)), 'c:/laragon/'), 'Unexpected MySQL version or data directory.');
    ensure($server->selected_db === $database, 'Wrong selected database.');
    runPrivate(['git', 'check-ignore', '--quiet', '.env']);
    $tracked = new Process(['git', 'ls-files', '--error-unmatch', '.env'], base_path());
    $tracked->run();
    ensure(! $tracked->isSuccessful(), '.env is tracked. Stop.');
    $target = '2026_11_05_000300_create_international_identifier_storage';
    $path = 'database/migrations/'.$target.'.php';
    $referenceName = 'vita_identification_test_20261105_232512_7911ee';
    $refConfig = $connection;
    $refConfig['database'] = $referenceName;
    $refConfig['url'] = null;
    config(['database.connections.identifier_reference' => $refConfig]);
    $reference = DB::connection('identifier_reference');
    $mode = $argv[1] ?? '--inspect';
    if ($mode === '--probe') {
        $crypto = new IdentifierCrypto(config('identification'));
        $payload = ['original' => 'synthetic-local-probe', 'normalized' => 'synthetic-local-probe'];
        ensure($crypto->decrypt($crypto->encrypt($payload, ['purpose' => 'restart-probe']), ['purpose' => 'restart-probe']) === $payload, 'Key roundtrip failed.');
        ensure(count($crypto->hashes(['purpose' => 'restart-probe'])) >= 1, 'HMAC unavailable.');
        echo json_encode(['fresh_process_keys_ready' => true, 'encryption_version' => $crypto->encryptionVersion, 'lookup_version' => $crypto->lookupVersion]).PHP_EOL;
        exit;
    }
    if ($mode === '--verify-installed') {
        $restoreName = $argv[2] ?? '';
        ensure(preg_match('/\Avita_identification_restore_[a-z0-9_]+\z/', $restoreName) === 1, 'An approved isolated restore reference is required.');
        $restoreConfig = $connection;
        $restoreConfig['database'] = $restoreName;
        $restoreConfig['url'] = null;
        config(['database.connections.identifier_final_backup' => $restoreConfig]);
        $original = snapshot(DB::connection('identifier_final_backup'));
        $current = snapshot($db);
        ensure(snapshot($db, true) === snapshot($reference, true), 'Installed schema differs from the validated reference. Stop.');
        foreach ($original['tables'] as $name => $state) {
            if ($name !== 'migrations') {
                if (($current['tables'][$name] ?? null) !== $state) {
                    $changedFields = [];
                    foreach ($state as $field => $value) {
                        if (($current['tables'][$name][$field] ?? null) !== $value) {
                            $changedFields[] = $field;
                        }
                    }
                    ensure(false, 'Historical table '.$name.' differs in '.implode(',', $changedFields).'. Stop.');
                }
            }
        }
        $oldTriggers = array_filter($current['triggers'], fn ($name) => ! str_starts_with($name, 'ident_'), ARRAY_FILTER_USE_KEY);
        ensure($oldTriggers === $original['triggers'], 'Existing trigger differs from backup. Stop.');
        $oldMigrations = DB::connection('identifier_final_backup')->table('migrations')->pluck('migration')->all();
        $nowMigrations = $db->table('migrations')->pluck('migration')->all();
        ensure(array_values(array_diff($nowMigrations, $oldMigrations)) === [$target] && array_diff($oldMigrations, $nowMigrations) === [], 'Unexpected migration registry changes.');
        $counts = [];
        foreach (['customer', 'user', 'patient'] as $kind) {
            $counts[$kind.'_identifiers'] = $db->table($kind.'_identifiers')->count();
            ensure($counts[$kind.'_identifiers'] === 0, 'Identifier records were unexpectedly populated.');
        }
        $constraintRows = $db->select('SELECT CONSTRAINT_TYPE,COUNT(*) AS total FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=? AND TABLE_NAME IN (?,?,?) GROUP BY CONSTRAINT_TYPE', [$database, 'customer_identifiers', 'user_identifiers', 'patient_identifiers']);
        $totals = [];
        foreach ($constraintRows as $row) {
            $totals[$row->CONSTRAINT_TYPE] = (int) $row->total;
        }
        ensure(($totals['PRIMARY KEY'] ?? 0) === 3 && ($totals['UNIQUE'] ?? 0) === 12 && ($totals['FOREIGN KEY'] ?? 0) === 14 && ($totals['CHECK'] ?? 0) === 15, 'Unexpected constraints.');
        $rules = $db->select('SELECT DELETE_RULE,COUNT(*) AS total FROM information_schema.REFERENTIAL_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=? AND TABLE_NAME IN (?,?,?) GROUP BY DELETE_RULE', [$database, 'customer_identifiers', 'user_identifiers', 'patient_identifiers']);
        ensure(count($rules) === 1 && $rules[0]->DELETE_RULE === 'RESTRICT' && (int) $rules[0]->total === 14, 'Unexpected deletion rules.');
        $newTriggers = count($current['triggers']) - count($oldTriggers);
        ensure($newTriggers === 11, 'Unexpected identifier triggers.');
        new IdentifierCrypto(config('identification'));
        echo json_encode(['database' => $database, 'installed_schema_matches_validated_reference' => true, 'historical_tables_match_backup' => true, 'existing_triggers_match_backup' => true, 'only_approved_migration_added' => true, 'identifier_rows' => $counts, 'constraints' => $totals, 'all_foreign_keys_restrict' => true, 'new_triggers' => $newTriggers, 'configured_keys_valid' => true, 'stage3_started' => false], JSON_PRETTY_PRINT).PHP_EOL;
        exit;
    }
    $schema = snapshot($db, true);
    $refSchema = snapshot($reference, true);
    foreach (['customer', 'user', 'patient'] as $kind) {
        unset($refSchema['tables'][$kind.'_identifiers']);
    }
    foreach (array_keys($refSchema['triggers']) as $trigger) {
        if (str_starts_with($trigger, 'ident_')) {
            unset($refSchema['triggers'][$trigger]);
        }
    }
    if ($schema !== $refSchema) {
        $differences = [];
        foreach (['tables', 'triggers'] as $section) {
            foreach (array_unique(array_merge(array_keys($schema[$section]), array_keys($refSchema[$section]))) as $name) {
                if (($schema[$section][$name] ?? null) !== ($refSchema[$section][$name] ?? null)) {
                    $differences[$section][] = $name;
                }
            }
        }
        $triggerFields = [];
        $referenceTriggers = [];
        foreach ($reference->select('SHOW TRIGGERS') as $trigger) {
            $referenceTriggers[$trigger->Trigger] = (array) $trigger;
        }
        foreach ($db->select('SHOW TRIGGERS') as $trigger) {
            foreach ((array) $trigger as $field => $value) {
                if ($field !== 'Created' && isset($referenceTriggers[$trigger->Trigger]) && $referenceTriggers[$trigger->Trigger][$field] !== $value) {
                    $triggerFields[$field] = ($triggerFields[$field] ?? 0) + 1;
                }
            }
        }
        $collations = $db->select('SELECT SCHEMA_NAME, DEFAULT_CHARACTER_SET_NAME, DEFAULT_COLLATION_NAME FROM information_schema.SCHEMATA WHERE SCHEMA_NAME IN (?,?)', [$database, $referenceName]);
        echo json_encode(['preflight_only' => true, 'database' => $database, 'differences' => $differences, 'differing_trigger_fields' => $triggerFields, 'schema_collations' => $collations], JSON_PRETTY_PRINT).PHP_EOL;
        ensure(false, 'Development schema differs from the validated baseline. Stop before changes.');
    }
    $migrations = $db->table('migrations')->pluck('migration')->all();
    ensure(! in_array($target, $migrations, true), 'Migration already applied. Stop for review.');
    $pending = array_values(array_diff(array_map(fn ($file) => basename($file, '.php'), glob(database_path('migrations/*.php'))), $migrations));
    ensure($pending === [$target], 'Unexpected pending migrations. Stop before changes.');
    $defaultsRow = $db->selectOne('SELECT DEFAULT_CHARACTER_SET_NAME AS charset,DEFAULT_COLLATION_NAME AS collation_name FROM information_schema.SCHEMATA WHERE SCHEMA_NAME=?', [$database]);
    ensure($defaultsRow->charset === 'utf8mb4' && $defaultsRow->collation_name === 'utf8mb4_0900_ai_ci', 'Unexpected database defaults.');
    $report = ['database' => $database, 'host' => $connection['host'], 'mysql' => $server->version, 'local_datadir_verified' => true, 'schema_matches_validated_baseline' => true, 'pending' => [$target], 'env_ignored_and_untracked' => true, 'schema_defaults' => (array) $defaultsRow, 'routine_count' => count($schema['routines']), 'event_count' => count($schema['events']), 'view_count' => count($schema['views'])];
    if ($mode === '--verify-backup') {
        $restoreName = $argv[2] ?? '';
        ensure(preg_match('/\Avita_identification_restore_[a-z0-9_]+\z/', $restoreName) === 1, 'Only an isolated restore schema can be checked.');
        $restoreConfig = $connection;
        $restoreConfig['database'] = $restoreName;
        $restoreConfig['url'] = null;
        config(['database.connections.identifier_restore_check' => $restoreConfig]);
        ensure(snapshot($db) === snapshot(DB::connection('identifier_restore_check')), 'Backup metadata or data differs. Stop.');
        echo json_encode($report + ['restored_backup_verified' => true, 'restore_database' => $restoreName], JSON_PRETTY_PRINT).PHP_EOL;
        exit;
    }
    if ($mode !== '--apply') {
        echo json_encode($report, JSON_PRETTY_PRINT).PHP_EOL;
        exit;
    }

    $before = snapshot($db);
    $identity = new Process(['whoami', '/user', '/fo', 'csv', '/nh']);
    $identity->mustRun();
    ensure(preg_match('/S-1-5-[0-9-]+/', $identity->getOutput(), $match) === 1, 'Cannot determine local user SID.');
    $backup = 'C:/laragon/backups/vita-identification-stage2/'.gmdate('Ymd_His').'_'.bin2hex(random_bytes(3));
    ensure(mkdir($backup, 0700, true), 'Cannot create protected backup directory.');
    protect($backup, $match[0], true);
    $envPath = base_path('.env');
    $envBefore = file_get_contents($envPath);
    ensure(copy($envPath, $backup.'/.env.before'), 'Cannot back up environment configuration.');
    $escape = fn ($value) => '"'.str_replace(['\\', '"', "\n", "\r"], ['\\\\', '\\"', '\\n', '\\r'], (string) $value).'"';
    $defaults = $backup.'/mysql-client.cnf';
    file_put_contents($defaults, "[client]\nhost=".$escape($connection['host'])."\nport=".(int) $connection['port']."\nuser=".$escape($connection['username'])."\npassword=".$escape($connection['password'])."\n");
    $bin = 'C:/laragon/bin/mysql/mysql-8.0.30-winx64/bin/';
    $dump = $backup.'/database.sql';
    $restoreName = 'vita_identification_restore_'.gmdate('Ymd_His').'_'.bin2hex(random_bytes(3));
    try {
        runPrivate([$bin.'mysqldump.exe', '--defaults-extra-file='.$defaults, '--single-transaction', '--routines', '--events', '--triggers', '--hex-blob', '--set-gtid-purged=OFF', '--no-tablespaces', '--column-statistics=0', '--result-file='.$dump, $database]);
        ensure(is_file($dump) && filesize($dump) > 0, 'Backup is empty.');
        ensure(preg_match('/\Avita_identification_restore_[a-z0-9_]+\z/', $restoreName) === 1, 'Unsafe restore schema.');
        $db->statement("CREATE DATABASE `$restoreName` CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci");
        $input = fopen($dump, 'rb');
        $restore = new Process([$bin.'mysql.exe', '--defaults-extra-file='.$defaults, '--database='.$restoreName], base_path());
        $restore->setInput($input);
        $restore->setTimeout(180);
        $restore->run();
        fclose($input);
        ensure($restore->isSuccessful(), 'Backup restore failed. Stop before changing development.');
        $restoreConfig = $connection;
        $restoreConfig['database'] = $restoreName;
        $restoreConfig['url'] = null;
        config(['database.connections.identifier_restore' => $restoreConfig]);
        ensure(snapshot(DB::connection('identifier_restore')) === $before, 'Restored backup does not match development. Stop.');
        ensure(snapshot($db) === $before, 'Development changed during backup. Stop.');
        $report += ['backup_directory' => $backup, 'backup_sha256' => hash_file('sha256', $dump), 'restore_database' => $restoreName, 'backup_restored_and_verified' => true];
    } finally {
        if (is_file($defaults)) {
            unlink($defaults);
        }
    }

    // Only configure unprovisioned v1 keys; never silently replace an existing keyring.
    $ident = config('identification');
    $present = ! empty($ident['encryption_keys']['v1']) || ! empty($ident['lookup_keys']['v1']);
    if ($present) {
        new IdentifierCrypto($ident);
        $report['existing_keys_preserved'] = true;
    } else {
        ensure(empty($ident['encryption_keys']['v2']) && empty($ident['lookup_keys']['v2']), 'Unexpected existing keyring. Stop.');
        ensure(($ident['encryption_version'] ?? 'v1') === 'v1' && ($ident['lookup_version'] ?? 'v1') === 'v1', 'Unexpected key version. Stop.');
        $aes = 'base64:'.base64_encode(random_bytes(32));
        $hmac = 'base64:'.base64_encode(random_bytes(32));
        $values = ['IDENTIFICATION_ENCRYPTION_VERSION' => 'v1', 'IDENTIFICATION_ENCRYPTION_KEY_V1' => $aes, 'IDENTIFICATION_LOOKUP_VERSION' => 'v1', 'IDENTIFICATION_LOOKUP_KEY_V1' => $hmac];
        $updated = $envBefore;
        foreach ($values as $name => $value) {
            ensure(preg_match_all('/^'.preg_quote($name, '/').'=/m', $updated) <= 1, 'Duplicate environment key definition. Stop.');
            $line = $name.'='.$value;
            $updated = preg_match('/^'.preg_quote($name, '/').'=/m', $updated) ? preg_replace('/^'.preg_quote($name, '/').'=[^\r\n]*/m', $line, $updated) : rtrim($updated).PHP_EOL.$line.PHP_EOL;
        }
        ensure(file_get_contents($envPath) === $envBefore, 'Environment changed during preparation. Stop.');
        protect($envPath, $match[0], false);
        ensure(file_put_contents($envPath, $updated, LOCK_EX) === strlen($updated), 'Cannot persist identification keys.');
        unset($aes,$hmac,$values,$updated);
        $report['new_independent_keys_persisted'] = true;
    }
    ensure(copy($envPath, $backup.'/.env.after'), 'Cannot back up persisted keys.');
    runPrivate([PHP_BINARY, __FILE__, '--probe']);
    $report['fresh_process_key_probe_passed'] = true;
    // Last gate before nontransactional DDL. There are no background importers or clinical writes here.
    ensure(snapshot($db) === $before && snapshot($db, true) === $schema, 'Unexpected database change before migration. Stop.');
    $exit = Artisan::call('migrate', ['--path' => $path, '--force' => true, '--database' => $db->getName()]);
    ensure($exit === 0, 'Migration failed; stop and inspect without automatic destructive rollback.');
    $after = snapshot($db);
    $finalSchema = snapshot($db, true);
    $validatedSchema = snapshot($reference, true);
    ensure($finalSchema === $validatedSchema, 'Post-migration schema differs from validated schema. Stop.');
    foreach ($before['tables'] as $table => $state) {
        if ($table !== 'migrations') {
            ensure($after['tables'][$table] === $state, 'An existing table changed unexpectedly. Stop.');
        }
    }
    ensure($db->table('migrations')->where('migration', $target)->count() === 1, 'Migration registry mismatch.');
    ensure($db->table('migrations')->count() === count($migrations) + 1, 'Unexpected migration records.');
    foreach (['customer', 'user', 'patient'] as $kind) {
        ensure($db->table($kind.'_identifiers')->count() === 0, 'Unexpected identifier records. Stop.');
    }
    runPrivate([PHP_BINARY, __FILE__, '--probe']);
    $report += ['migration_applied' => $target, 'schema_matches_validated_result' => true, 'existing_tables_and_relationships_unchanged' => true, 'identifier_tables_empty' => true, 'stage3_started' => false];
    file_put_contents($backup.'/application-report.json', json_encode($report, JSON_PRETTY_PRINT));
    echo json_encode($report, JSON_PRETTY_PRINT).PHP_EOL;
} catch (Throwable $e) {
    // Suppress exception chains, SQL and environment values. Messages below are deliberate guards.
    if ($e instanceof \Illuminate\Database\QueryException || $e instanceof \Symfony\Component\Process\Exception\ProcessFailedException) {
        fwrite(STDERR, "Controlled operation stopped: database/process error. No automatic rollback or historical import.\n");
    } else {
        fwrite(STDERR, 'Controlled operation stopped: '.$e->getMessage().PHP_EOL);
    }
    exit(1);
}
