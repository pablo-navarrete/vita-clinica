<?php

namespace App\Console\Commands;

use App\Models\{Patient, PortalAccount};
use App\Services\MiSalud\PortalPatientLinkService;
use Illuminate\Console\Command;

class ReconcilePortalPatients extends Command
{
    protected $signature='mi-salud:reconcile-patients {--index-existing : Index existing declared documents without modifying source records} {--limit=100}';
    protected $description='Reconcile documentary candidates only; never grants clinical access.';

    public function handle(PortalPatientLinkService $links): int
    {
        if ($this->option('index-existing')) {
            foreach (['account'=>PortalAccount::class,'patient'=>Patient::class] as $kind=>$model) {
                $model::withoutGlobalScopes()->select('id')->orderBy('id')->chunkById(100,function($rows) use($links,$kind) {
                    foreach ($rows as $row) $links->request($kind,(int)$row->id);
                });
            }
        }
        $count=$links->drain(max(1,min(1000,(int)$this->option('limit'))));
        $this->info('Processed pending requests: '.$count.'. No clinical access granted.');
        return self::SUCCESS;
    }
}
