<?php

use App\Models\Site;
use App\Services\SeoService;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('site:seo-audit {siteId}', function ($siteId) {
    $site = Site::find($siteId);
    if (! $site) {
        $this->error("Site not found: {$siteId}");
        return 1;
    }

    $log = app(SeoService::class)->runSeoCheck($site->id);
    $this->info("SEO audit completed for {$site->url}. Score: {$log->score}. Lighthouse: " . ($log->lighthouse ? 'stored' : 'skipped'));
    return 0;
})->purpose('Run an SEO audit with Lighthouse for a site by ID');

Schedule::job(new \App\Jobs\DispatchDueChecksJob)->everyMinute();
Schedule::job(new \App\Jobs\ZombieRescueJob)->everyFiveMinutes();
Schedule::job(new \App\Jobs\DailySummaryJob)->dailyAt('00:30');
Schedule::job(new \App\Jobs\RetentionCycleJob)->dailyAt('03:00');
