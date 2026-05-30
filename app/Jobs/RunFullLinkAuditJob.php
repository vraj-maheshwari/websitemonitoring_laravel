<?php

namespace App\Jobs;

use App\Models\FullLinkAuditLog;
use App\Models\Site;
use App\Services\FullLinkAuditService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class RunFullLinkAuditJob implements ShouldQueue
{
    use Queueable;

    public function __construct(public int $siteId, public int $auditId) {}

    public function handle(FullLinkAuditService $service): void
    {
        $site = Site::findOrFail($this->siteId);
        $audit = FullLinkAuditLog::findOrFail($this->auditId);
        $audit->update(['status' => 'running', 'started_at' => now()]);

        try {
            $results = $service->runFullLinkAudit($site->url, $audit->max_depth, $audit->max_pages, 100, fn ($progress) => $audit->update($progress));
            $audit->update(['status' => 'done', 'completed_at' => now(), 'results' => $results, 'pages_crawled' => count($results['pages']), 'links_checked' => count($results['links'])]);
        } catch (\Throwable $exception) {
            $audit->update(['status' => 'failed', 'completed_at' => now(), 'error_message' => $exception->getMessage()]);
            throw $exception;
        }
    }
}
