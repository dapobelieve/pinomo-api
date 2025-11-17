<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    protected function schedule(Schedule $schedule): void
    {
        // Run end-of-day processing at 11:55 PM
        $schedule->command('banking:end-of-day')
            ->dailyAt('23:55')
            ->withoutOverlapping()
            ->appendOutputTo(storage_path('logs/end-of-day.log'));

        // Run aggregates reset at midnight
        $schedule->command('aggregates:reset')
            ->dailyAt('00:00')
            ->withoutOverlapping()
            ->after(function () {
                // Additional cleanup or verification tasks can be added here
            });
    }

    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');
    }
}