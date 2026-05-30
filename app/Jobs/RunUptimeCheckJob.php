<?php

namespace App\Jobs;

use App\Jobs\Concerns\RunsSiteCheck;
use App\Services\UptimeService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class RunUptimeCheckJob implements ShouldQueue
{
    use Queueable, RunsSiteCheck;

    public function __construct(public int $siteId) {}

    public function handle(UptimeService $service): void
    {
        $site = $this->markRunning($this->siteId, 'uptime');
        if (! $site) return;

        try {
            $service->runUptimeCheck($this->siteId);
        } catch (\Throwable $exception) {
            $this->failCheck($site, 'uptime', $exception);
            throw $exception;
        }
    }
}
