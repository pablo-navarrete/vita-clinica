<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     */
    protected function schedule(Schedule $schedule): void
    {
        $schedule->command('mi-salud:reconcile-patients')->everyMinute()->withoutOverlapping();
        $schedule->command('patients:cleanup-registrations')->daily()->withoutOverlapping();
        $schedule->command('signatures:cleanup')->hourly()->withoutOverlapping();
        $schedule->command('flow:sync-fees')->hourly()->withoutOverlapping();
        $schedule->command('flow:reconcile-subscriptions')->everyFiveMinutes()->withoutOverlapping();
        $schedule->command('flow:send-fee-alerts')->hourly()->withoutOverlapping();
        // $schedule->command('inspire')->hourly();
        $schedule->command('appointments:update-status')
        ->everyFiveMinutes();
    }

    /**
     * Register the commands for the application.
     */
    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
