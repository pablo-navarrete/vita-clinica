<?php

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use Illuminate\Support\Facades\DB;
use Symfony\Component\Process\Process;

if ($app->configurationIsCached() || DB::getDriverName() !== 'mysql') {
    throw new RuntimeException('Uncached MySQL configuration required.');
}
$original = DB::connection();
if (! in_array($original->getConfig('host'), ['127.0.0.1', 'localhost', '::1'], true) || $original->getDatabaseName() !== 'vita_clinica') {
    throw new RuntimeException('This local validation tool requires the approved Laragon development connection.');
}
$fingerprint = function () use ($original) {
    $result = [];
    foreach ($original->select("SHOW FULL TABLES WHERE Table_type='BASE TABLE'") as $row) {
        $name = array_values((array) $row)[0];
        $quoted = '`'.str_replace('`', '``', $name).'`';
        $result[$name] = [$original->table($name)->count(), $original->selectOne('CHECKSUM TABLE '.$quoted)->Checksum, hash('sha256', json_encode($original->selectOne('SHOW CREATE TABLE '.$quoted)))];
    }

    $result['_triggers'] = hash('sha256', json_encode($original->select('SHOW TRIGGERS')));

    return $result;
};
$before = $fingerprint();
$env = hash_file('sha256', base_path('.env'));
$database = 'vita_identification_test_mi_salud_'.gmdate('Ymd_His').'_'.bin2hex(random_bytes(3));
if (! preg_match('/\Avita_identification_test_mi_salud_[a-z0-9_]+\z/', $database)) {
    throw new RuntimeException('Invalid isolated schema.');
}
$original->statement("CREATE DATABASE `$database` CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci");
$connection = $original->getConfig();
$connection['database'] = $database;
$connection['url'] = null;
config(['database.connections.identification_stage3' => $connection, 'database.default' => 'identification_stage3']);
$report = ['database' => $database];
try {
    // Bootstrap Laravel in a separate process with the isolated DB selected from startup.
    // A Schema facade resolved before changing the default connection must never be reused.
    $testEnv = ['DB_DATABASE' => $database, 'DB_CONNECTION' => 'mysql', 'DATABASE_URL' => false, 'VITA_IDENTIFIER_STORAGE_APPROVED' => 'yes', 'APP_ENV' => 'testing', 'CACHE_DRIVER' => 'array', 'SESSION_DRIVER' => 'array', 'MAIL_MAILER' => 'array', 'QUEUE_CONNECTION' => 'sync'];
    $testEnv += ['DB_HOST' => $connection['host'], 'DB_PORT' => (string) $connection['port'], 'DB_USERNAME' => $connection['username'], 'DB_PASSWORD' => $connection['password']];
    $migration = new Process([PHP_BINARY, base_path('artisan'), 'migrate', '--force'], base_path(), $testEnv);
    $migration->setTimeout(180);
    $migration->run();
    if (! $migration->isSuccessful()) {
        echo $migration->getOutput();
        throw new RuntimeException('Isolated migration failed.');
    }
    $process = new Process([PHP_BINARY, base_path('vendor/phpunit/phpunit/phpunit'), 'tests/Feature/MiSaludAccountTest.php', 'tests/Integration/Identification', 'tests/Unit/Identification', 'tests/Feature/CustomerAdministratorOnboardingTest.php', 'tests/Feature/ProfileAssignmentsTest.php', 'tests/Feature/ProfessionalSignatureTest.php', 'tests/Feature/EncounterSigningTest.php', 'tests/Feature/EncounterAddendumTest.php', 'tests/Feature/CustomerSubscriptionTest.php', 'tests/Feature/ClinicBillingPortalTest.php', 'tests/Feature/FlowFeeMonitorTest.php', 'tests/Feature/CustomerDirectoryTest.php', 'tests/Feature/SuperAdminBranchEditTest.php', 'tests/Feature/VitiBookingConversationTest.php', 'tests/Feature/FileSpecialtyCatalogTest.php'], base_path(), $testEnv);
    $process->setTimeout(180);
    $process->run(fn ($type, $buffer) => print ($buffer));
    $report['test_exit_code'] = $process->getExitCode();
} finally {
    $report['habitual_database_unchanged'] = $before === $fingerprint();
    $report['env_unchanged'] = $env === hash_file('sha256', base_path('.env'));
    file_put_contents(storage_path('logs/mi-salud-'.$database.'.json'), json_encode($report, JSON_PRETTY_PRINT));
    echo json_encode($report, JSON_PRETTY_PRINT).PHP_EOL;
}
if (($report['test_exit_code'] ?? 1) !== 0 || ! $report['habitual_database_unchanged'] || ! $report['env_unchanged']) {
    exit(1);
}
