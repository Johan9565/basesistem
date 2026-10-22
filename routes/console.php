<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('assistant:process-reminders')->everyMinute();
Schedule::command('assistant:dispatch-reminders')->everyMinute();
Schedule::command('assistant:send-daily-digest')->dailyAt('08:00')->timezone('America/Cancun');

