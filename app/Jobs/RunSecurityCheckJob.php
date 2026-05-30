<?php

namespace App\Jobs;

use App\Jobs\Concerns\RunsSiteCheck;
use App\Services\SecurityService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class RunSecurityCheckJob implements ShouldQueue
{
    use Queueable, RunsSiteCheck;
    public function __construct(public int $siteId) {}
    public function handle(SecurityService $service): void
    {
        $site = $this->markRunning($this->siteId, 'security');
        if (! $site) return;
        try { $service->runSecurityCheck($this->siteId); } catch (\Throwable $e) { $this->failCheck($site, 'security', $e); throw $e; }
    }
}
