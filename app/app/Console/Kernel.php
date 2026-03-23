<?php

declare(strict_types=1);

namespace App\Console;

use App\Console\Commands\CreateUserAndSendWelcome;
use App\Console\Commands\SendLeadFollowUpReminders;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * @var array<int, class-string<\Illuminate\Console\Command>>
     */
    protected $commands = [
        CreateUserAndSendWelcome::class,
        SendLeadFollowUpReminders::class,
    ];

    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');
    }

    protected function schedule(Schedule $schedule): void
    {
        $schedule->command('schedule:send-reminders')->everyThirtyMinutes();
        $schedule->command('leads:send-follow-up-reminders')->dailyAt('08:00');
    }
}
