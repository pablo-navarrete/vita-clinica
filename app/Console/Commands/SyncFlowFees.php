<?php
namespace App\Console\Commands;
use Illuminate\Console\Command;
use App\Services\Billing\{FlowFeeMonitor,FlowReadClient};
class SyncFlowFees extends Command
{
    protected $signature='flow:sync-fees {--date= : Fecha de pagos YYYY-MM-DD}';
    protected $description='Consulta pagos y comisiones Flow sin ejecutar cobros';
    public function handle(FlowReadClient $client,FlowFeeMonitor $monitor): int
    {
        if(!$client->configured()) { $this->warn('Flow no está habilitado/configurado. No se realizó ninguna consulta.'); return self::SUCCESS; }
        try {
            $dates=$this->option('date')?[$this->option('date')]:[now('America/Santiago')->subDay()->toDateString(),now('America/Santiago')->toDateString()];
            foreach($dates as $date) $this->info($date.': '.$monitor->sync($date).' pagos revisados.');
            return self::SUCCESS;
        } catch(\Throwable $e) { $this->error('No se completó la sincronización de Flow. Revisa la configuración o ejecuta una fecha individual desde SuperAdmin.'); return self::FAILURE; }
    }
}
