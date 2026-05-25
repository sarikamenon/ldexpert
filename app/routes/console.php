<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('schedule:send-reminders')->everyThirtyMinutes();
Schedule::command('leads:send-follow-up-reminders')->dailyAt('08:00');
Schedule::command('billing:generate')->dailyAt('02:00');
Schedule::command('billing:send-reminders')->dailyAt('08:00');
Schedule::command('school:notify-expiring-contracts')->dailyAt('08:00');
Schedule::command('school:auto-extend-contracts-ssas')->dailyAt('02:00');
Schedule::command('sub-requests:expire-overdue')->hourly();
Schedule::command('makeup-reminders:generate')->dailyAt('03:00');
Schedule::command('makeup-reminders:send-due')->dailyAt('07:00');
Schedule::command('makeup-reminders:auto-decline')->dailyAt('04:00');
