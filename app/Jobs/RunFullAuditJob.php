<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class RunFullAuditJob implements ShouldQueue
{
    use Queueable;

    public function __construct(public int $siteId) {}

    public function handle(): void
    {
        RunUptimeCheckJob::dispatch($this->siteId);
        RunSslCheckJob::dispatch($this->siteId);
        RunSeoCheckJob::dispatch($this->siteId);
        RunSecurityCheckJob::dispatch($this->siteId);
        RunDnsCheckJob::dispatch($this->siteId);
    }
}
