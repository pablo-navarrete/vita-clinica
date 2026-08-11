<?php

namespace App\Console\Commands;

use App\Models\Appointment;
use Illuminate\Console\Command;

class UpdateAppointmentsStatus extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'appointments:update-status';

    /**
     * The console command description.
     */
    protected $description = 'Actualiza automáticamente el estado de las citas vencidas';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $updated = Appointment::whereIn('status', [0, 1, 2])
            ->where('end_at', '<', now())
            ->update([
                'status' => 4 // No asistió
            ]);

        $this->info("Citas actualizadas: {$updated}");

        return self::SUCCESS;
    }
}