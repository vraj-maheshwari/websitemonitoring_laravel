<?php

namespace App\Jobs;

use App\Jobs\Concerns\RunsSiteCheck;
use App\Services\SslService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class RunSslCheckJob implements ShouldQueue
{
    use Queueable, RunsSiteCheck;
    public function __construct(public int $siteId) {}
    public function handle(SslService $service): void
    {
        $site = $this->markRunning($this->siteId, 'ssl');
        if (! $site) return;
        try { $service->runSslCheck($this->siteId); } catch (\Throwable $e) { $this->failCheck($site, 'ssl', $e); throw $e; }
    }
}
