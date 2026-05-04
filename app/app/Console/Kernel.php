<?php

declare(strict_types=1);

namespace App\Console;

use App\Console\Commands\BillingGenerate;
use App\Console\Commands\BillingSendReminders;
use App\Console\Commands\CreateUserAndSendWelcome;
use App\Console\Commands\SchoolContractAutoExtend;
use App\Console\Commands\SchoolContractExpiryNotify;
use App\Console\Commands\SendLeadFollowUpReminders;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * @var array<int, class-string<\Illuminate\Console\Command>>
     */
    protected $commands = [
        BillingGenerate::class,
        BillingSendReminders::class,
        CreateUserAndSendWelcome::class,
        SchoolContractAutoExtend::class,
        SchoolContractExpiryNotify::class,
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
        $schedule->command('billing:generate')->dailyAt('02:00');
        $schedule->command('billing:send-reminders')->dailyAt('08:00');
        $schedule->command('school:notify-expiring-contracts')->dailyAt('08:00');
        $schedule->command('school:auto-extend-contracts-ssas')->dailyAt('02:00');
    }
}
