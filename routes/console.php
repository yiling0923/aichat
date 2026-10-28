<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Windows: Task Scheduler runs "php artisan schedule:run" every minute (see README).
Schedule::command('aichat:check-handovers')->everyMinute()->withoutOverlapping();
Schedule::command('aichat:send-reminders')->everyMinute()->withoutOverlapping();
Schedule::command('aichat:run-automations')->everyFiveMinutes()->withoutOverlapping();
Schedule::command('aichat:renew-subscriptions')->dailyAt('03:10')->withoutOverlapping();
