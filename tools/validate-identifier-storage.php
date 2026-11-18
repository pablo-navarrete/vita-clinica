<?php

// Explicit technical validation only. Never migrates the habitual database.
require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Process\Process;

if ($app->configurationIsCached()) {
    throw new RuntimeException('Use uncached configuration for isolated validation.');
}
$habitual = DB::connection();
if ($habitual->getDriverName() !== 'mysql') {
    throw new RuntimeException('MySQL required.');
}
$snapshot = function ($connection, bool $normalizeCounters = false): array {
    $tables = $connection->select('SHOW FULL TABLES WHERE Table_type = \'BASE TABLE\'');
    $result = [];
    foreach ($tables as $table) {
        $name = array_values((array) $table)[0];
        $quoted = '`'.str_replace('`', '``', $name).'`';
        $result[$name] = [
            'ddl' => hash('sha256', $normalizeCounters ? preg_replace('/ AUTO_INCREMENT=\d+/', '', json_encode($connection->select('SHOW CREATE TABLE '.$quoted))) : json_encode($connection->select('SHOW CREATE TABLE '.$quoted))),
            'checksum' => $connection->select('CHECKSUM TABLE '.$quoted)[0]->Checksum,
            'rows' => $connection->table($name)->count(),
        ];
    }
    ksort($result);
    $result['_triggers'] = hash('sha256', json_encode($connection->select('SHOW TRIGGERS')));

    return $result;
};
$before = $snapshot($habitual);
$envFingerprint = hash_file('sha256', base_path('.env'));
$schemaSettings = $habitual->selectOne('SELECT DEFAULT_CHARACTER_SET_NAME AS charset, DEFAULT_COLLATION_NAME AS collation_name FROM information_schema.SCHEMATA WHERE SCHEMA_NAME=?', [$habitual->getDatabaseName()]);
if ($schemaSettings->charset !== 'utf8mb4' || $schemaSettings->collation_name !== 'utf8mb4_0900_ai_ci') {
    throw new RuntimeException('Unexpected habitual charset/collation. Stop.');
}
$settings = fn ($connection) => (array) $connection->selectOne('SELECT VERSION() AS version, @@session.sql_mode AS sql_mode, @@session.time_zone AS time_zone, @@session.character_set_client AS character_set_client, @@session.character_set_connection AS character_set_connection, @@session.character_set_results AS character_set_results, @@session.collation_connection AS collation_connection, @@lower_case_table_names AS lower_case_table_names, @@default_storage_engine AS default_storage_engine, @@session.foreign_key_checks AS foreign_key_checks, @@session.unique_checks AS unique_checks, @@session.transaction_isolation AS transaction_isolation, @@explicit_defaults_for_timestamp AS explicit_defaults_for_timestamp');
$triggerMetadata = function ($connection): array {
    $result = [];
    foreach ($connection->select('SHOW TRIGGERS') as $trigger) {
        $row = (array) $trigger;
        unset($row['Created']);
        $result[$trigger->Trigger] = $row;
    }
    ksort($result);

    return $result;
};
$habitualSettings = $settings($habitual);
$habitualTriggers = $triggerMetadata($habitual);
$database = 'vita_identification_test_'.gmdate('Ymd_His').'_'.bin2hex(random_bytes(3));
if (! preg_match('/\Avita_identification_test_[a-z0-9_]+\z/', $database) || $database === $habitual->getDatabaseName()) {
    throw new RuntimeException('Unsafe schema.');
}
$report = ['database' => $database, 'mysql' => $habitual->selectOne('SELECT VERSION() AS version')->version];
try {
    $habitual->statement("CREATE DATABASE `$database` CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci");
    $connection = config('database.connections.mysql');
    $connection['database'] = $database;
    $connection['url'] = null;
    config(['database.connections.identifier_validation' => $connection, 'database.default' => 'identifier_validation']);
    $isolated = DB::connection('identifier_validation');
    if ($isolated->getDatabaseName() !== $database) {
        throw new RuntimeException('Wrong database.');
    }
    $report['schema_defaults'] = (array) $schemaSettings;
    $report['relevant_mysql_settings'] = $settings($isolated);
    if ($habitualSettings !== $settings($isolated)) {
        throw new RuntimeException('Unexpected session/server setting differences. Stop.');
    }
    $report['relevant_settings_match'] = true;
    $target = 'database/migrations/2026_11_05_000300_create_international_identifier_storage.php';
    $base = array_values(array_filter(glob(database_path('migrations/*.php')), fn ($file) => basename($file) !== basename($target)));
    $run = function ($command, $args) {
        $code = Artisan::call($command, $args + ['--database' => 'identifier_validation', '--force' => true]);
        if ($code !== 0) {
            throw new RuntimeException(Artisan::output());
        }
    };
    $run('migrate', ['--path' => $base, '--realpath' => true]);
    $reproduced = $snapshot($isolated, true);
    $habitualSchema = $snapshot($habitual, true);
    $schemaMatches = array_keys($reproduced) === array_keys($habitualSchema);
    foreach ($habitualSchema as $name => $state) {
        if ($name !== '_triggers' && ($reproduced[$name]['ddl'] ?? null) !== $state['ddl']) {
            $schemaMatches = false;
        }
    }
    $report['base_table_definitions_match_habitual'] = $schemaMatches;
    $report['base_trigger_metadata_match_habitual'] = $triggerMetadata($isolated) === $habitualTriggers;
    if (! $schemaMatches || ! $report['base_trigger_metadata_match_habitual']) {
        throw new RuntimeException('Unexpected reproduced schema/trigger difference. Stop before identification migration.');
    }
    // Pre-existing synthetic historical documents, deliberately not normalised or imported.
    $now = date('Y-m-d H:i:s');
    $cid = $isolated->table('customers')->insertGetId(['name' => 'Historical fixture', 'legal_name' => 'Test', 'holder' => 'Test', 'rut' => 'legacy-0001', 'domain' => 'history-validation', 'email' => 'history@test.test', 'phone' => '123', 'status' => 1, 'created_at' => $now, 'updated_at' => $now]);
    $uid = $isolated->table('users')->insertGetId(['customer_id' => $cid, 'name' => 'Historical fixture', 'rut' => 'legacy-0002', 'email' => 'history-user@test.test', 'password' => password_hash('synthetic-only', PASSWORD_BCRYPT), 'status' => 1, 'created_at' => $now, 'updated_at' => $now]);
    $pid = $isolated->table('patients')->insertGetId(['customer_id' => $cid, 'name' => 'Historical fixture', 'rut' => 'legacy-0003', 'gender' => 'male', 'created_at' => $now, 'updated_at' => $now]);
    $history = function () use ($isolated, $cid, $uid, $pid) {
        return [$isolated->table('customers')->find($cid), $isolated->table('users')->find($uid), $isolated->table('patients')->find($pid)];
    };
    $historyBefore = json_encode($history());
    $baseline = $snapshot($isolated, true);
    $run('migrate', ['--path' => $target]);
    $report['migration_applied'] = true;
    $existingTriggers = array_filter($triggerMetadata($isolated), fn ($name) => ! str_starts_with($name, 'ident_'), ARRAY_FILTER_USE_KEY);
    $report['existing_trigger_metadata_preserved'] = $existingTriggers === $habitualTriggers;
    if (! $report['existing_trigger_metadata_preserved']) {
        throw new RuntimeException('Existing trigger metadata changed. Stop.');
    }
    $migrated = $snapshot($isolated, true);
    $report['existing_tables_preserved_during_migration'] = true;
    foreach ($baseline as $name => $state) {
        if ($name !== '_triggers' && $name !== 'migrations' && $migrated[$name] !== $state) {
            $report['existing_tables_preserved_during_migration'] = false;
        }
    }
    $run('migrate:rollback', ['--path' => $target, '--step' => 1]);
    $report['empty_rollback_restores_baseline'] = $baseline === $snapshot($isolated, true);
    $run('migrate', ['--path' => $target]);
    $checks = $isolated->select('SELECT TABLE_NAME, CONSTRAINT_TYPE, COUNT(*) AS total FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=? AND TABLE_NAME IN (?,?,?) GROUP BY TABLE_NAME,CONSTRAINT_TYPE', [$database, 'customer_identifiers', 'user_identifiers', 'patient_identifiers']);
    $report['constraints'] = $checks;
    $report['identifier_trigger_count'] = count(array_filter($isolated->select('SHOW TRIGGERS'), fn ($row) => str_starts_with($row->Trigger, 'ident_')));
    $report['foreign_key_delete_rules'] = $isolated->select('SELECT DELETE_RULE, COUNT(*) AS total FROM information_schema.REFERENTIAL_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=? AND TABLE_NAME IN (?,?,?) GROUP BY DELETE_RULE', [$database, 'customer_identifiers', 'user_identifiers', 'patient_identifiers']);
    $report['lookup_explain'] = $isolated->select('EXPLAIN SELECT record_uuid FROM patient_identifiers WHERE scope_key=? AND lookup_key_version=? AND lookup_hash=? AND retired_at IS NULL', ['customer:'.$cid, 'v1', str_repeat('a', 64)]);
    $report['lookup_column_collations'] = $isolated->select('SELECT TABLE_NAME,COLUMN_NAME,COLLATION_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=? AND TABLE_NAME IN (?,?,?) AND COLUMN_NAME IN (?,?,?,?) ORDER BY TABLE_NAME,COLUMN_NAME', [$database, 'customer_identifiers', 'user_identifiers', 'patient_identifiers', 'scope_key', 'issuing_country_code', 'lookup_hash', 'claim_hash']);
    $process = new Process([PHP_BINARY, base_path('vendor/phpunit/phpunit/phpunit'), 'tests/Integration/Identification', 'tests/Unit/Identification'], base_path(), [
        'DB_DATABASE' => $database, 'DB_CONNECTION' => 'mysql', 'DATABASE_URL' => false, 'VITA_IDENTIFIER_STORAGE_APPROVED' => 'yes',
        'APP_ENV' => 'testing', 'CACHE_DRIVER' => 'array', 'SESSION_DRIVER' => 'array', 'QUEUE_CONNECTION' => 'sync', 'MAIL_MAILER' => 'array',
    ]);
    $process->setTimeout(180);
    $process->run(function ($type, $buffer) {
        echo $buffer;
    });
    $report['test_exit_code'] = $process->getExitCode();
    $report['synthetic_historical_rows_unchanged'] = $historyBefore === json_encode($history());
    $after = $snapshot($isolated, true);
    $report['existing_isolated_tables_unchanged'] = true;
    // Tests intentionally use synthetic records; original table schemas must remain identical.
    foreach ($baseline as $name => $state) {
        if ($name !== '_triggers' && $name !== 'migrations' && $after[$name]['ddl'] !== $state['ddl']) {
            $report['existing_isolated_tables_unchanged'] = false;
        }
    }
} finally {
    $report['habitual_schema_and_rows_unchanged'] = $before === $snapshot($habitual);
    $report['env_unchanged'] = $envFingerprint === hash_file('sha256', base_path('.env'));
    $path = storage_path('logs/identifier-validation-'.$database.'.json');
    file_put_contents($path, json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    echo json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE).PHP_EOL;
    echo 'Report: '.$path.PHP_EOL;
}
foreach (['relevant_settings_match', 'base_table_definitions_match_habitual', 'base_trigger_metadata_match_habitual', 'existing_trigger_metadata_preserved', 'env_unchanged', 'migration_applied', 'empty_rollback_restores_baseline', 'existing_tables_preserved_during_migration', 'existing_isolated_tables_unchanged', 'synthetic_historical_rows_unchanged', 'habitual_schema_and_rows_unchanged'] as $required) {
    if (empty($report[$required])) {
        exit(1);
    }
}
if (($report['test_exit_code'] ?? 1) !== 0) {
    exit(1);
}
