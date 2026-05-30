<?php

namespace App\Jobs;

use App\Jobs\Concerns\RunsSiteCheck;
use App\Services\SeoService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class RunSeoCheckJob implements ShouldQueue
{
    use Queueable, RunsSiteCheck;
    public function __construct(public int $siteId) {}
    public function handle(SeoService $service): void
    {
        $site = $this->markRunning($this->siteId, 'seo');
        if (! $site) return;
        try { $service->runSeoCheck($this->siteId); } catch (\Throwable $e) { $this->failCheck($site, 'seo', $e); throw $e; }
    }
}
