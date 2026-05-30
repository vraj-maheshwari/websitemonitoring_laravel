<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::job(new \App\Jobs\DispatchDueChecksJob)->everyMinute();
Schedule::job(new \App\Jobs\ZombieRescueJob)->everyFiveMinutes();
Schedule::job(new \App\Jobs\DailySummaryJob)->dailyAt('00:30');
Schedule::job(new \App\Jobs\RetentionCycleJob)->dailyAt('03:00');
