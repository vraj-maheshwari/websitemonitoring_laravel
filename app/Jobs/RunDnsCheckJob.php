<?php

namespace App\Jobs;

use App\Jobs\Concerns\RunsSiteCheck;
use App\Models\Site;
use App\Services\DnsService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class RunDnsCheckJob implements ShouldQueue
{
    use Queueable, RunsSiteCheck;
    public function __construct(public int $siteId) {}
    public function handle(DnsService $service): void
    {
        $site = $this->markRunning($this->siteId, 'dns');
        if (! $site) return;
        try { $service->runDnsCheck(Site::findOrFail($this->siteId)); } catch (\Throwable $e) { $this->failCheck($site, 'dns', $e); throw $e; }
    }
}
