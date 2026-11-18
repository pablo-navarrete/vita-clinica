<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class CleanupPatientRegistrations extends Command
{
    protected $signature = 'patients:cleanup-registrations';
    protected $description = 'Elimina solicitudes públicas pendientes vencidas; conserva recibos confirmados y registros clínicos.';
    public function handle(): int
    {
        $this->info((string) app(\App\Services\Patients\PublicPatientRegistration::class)->purgeExpired().' solicitudes pendientes eliminadas.');
        return self::SUCCESS;
    }
}
