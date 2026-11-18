<?php

namespace App\Console\Commands;

use App\Services\Identification\History\HistoricalIdentifierDiagnostic;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

final class DiagnoseHistoricalIdentifiers extends Command
{
    protected $signature = 'identification:diagnose';

    protected $description = 'Diagnóstico de solo lectura y conteos anónimos de identificadores históricos; nunca migra datos';

    public function handle(HistoricalIdentifierDiagnostic $diagnostic): int
    {
        // A consistent read-only transaction. No schema operations or historical writes.
        if (DB::getDriverName() !== 'mysql' || DB::transactionLevel() !== 0) {
            $this->error('Ejecuta este diagnóstico en una conexión MySQL independiente, sin transacción previa.');

            return self::FAILURE;
        }
        DB::statement('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ, READ ONLY');
        $report = DB::transaction(function () use ($diagnostic) {
            $sources = [];
            foreach (['customers', 'users', 'patients'] as $table) {
                $columns = $table === 'customers' ? ['id', 'rut', 'deleted_at'] : ($table === 'users' ? ['id', 'customer_id', 'rut', 'deleted_at'] : ['id', 'customer_id', 'rut']);
                $sources[$table] = DB::table($table)->select($columns)->lazyById(500);
            }

            return $diagnostic->summarize($sources);
        });
        $this->line(json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));

        return self::SUCCESS;
    }
}
