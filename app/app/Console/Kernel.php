<?php

declare(strict_types=1);

namespace App\Console;

use App\Console\Commands\CreateUserAndSendWelcome;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');
        $this->commands([
            CreateUserAndSendWelcome::class,
        ]);
    }

    protected function schedule(Schedule $schedule): void
    {
        $schedule->command('schedule:send-reminders')->everyThirtyMinutes();
    }
}
