<?php

namespace App\Services;

use App\Models\DnsLog;
use App\Models\Incident;
use App\Models\SeoLog;
use App\Models\SslLog;
use App\Models\UptimeLog;

class RetentionService
{
    public function __construct(private SummaryService $summaries) {}

    public function runRetentionCycle(): void
    {
        $this->summaries->runDailySummary(now()->subDays((int) env('DATA_RETENTION_DAYS', 90)));
        $cutoff = now()->subDays((int) env('DATA_RETENTION_DAYS', 90));

        foreach ([UptimeLog::class, SslLog::class, SeoLog::class, DnsLog::class] as $model) {
            $model::where('created_at', '<', $cutoff)->delete();
        }

        Incident::where('status', 'RESOLVED')->where('resolved_at', '<', now()->subDays(180))->delete();
    }
}
