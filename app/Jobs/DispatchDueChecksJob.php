<?php

namespace App\Jobs;

use App\Services\MonitoringService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class DispatchDueChecksJob implements ShouldQueue
{
    use Queueable;

    public function handle(MonitoringService $monitoring): void
    {
        $map = [
            'uptime' => RunUptimeCheckJob::class,
            'ssl' => RunSslCheckJob::class,
            'seo' => RunSeoCheckJob::class,
            'security' => RunSecurityCheckJob::class,
            'dns' => RunDnsCheckJob::class,
        ];

        foreach ($map as $type => $job) {
            foreach ($monitoring->getDueSiteIds($type) as $siteId) {
                $job::dispatch($siteId);
            }
        }
    }
}
