<?php

namespace App\Services;

use App\Models\Site;

class ReportService
{
    public function generateSiteReport(int $siteId): array
    {
        $site = Site::with(['uptimeLogs' => fn ($q) => $q->latest('checked_at')->limit(30), 'sslLogs' => fn ($q) => $q->latest('checked_at')->limit(10), 'seoLogs' => fn ($q) => $q->latest('checked_at')->limit(10), 'dnsLogs' => fn ($q) => $q->latest('checked_at')->limit(10), 'incidents' => fn ($q) => $q->latest('opened_at')->limit(10)])->findOrFail($siteId);

        return [
            'generated_at' => now()->toIso8601String(),
            'site' => $site->toViewArray(),
            'uptime_logs' => $site->uptimeLogs,
            'ssl_logs' => $site->sslLogs,
            'seo_logs' => $site->seoLogs,
            'dns_logs' => $site->dnsLogs,
            'incidents' => $site->incidents,
        ];
    }

    public function generateSiteCsvReport(int $siteId): string
    {
        $report = $this->generateSiteReport($siteId);
        $rows = ["type,checked_at,status,status_code,response_time"];
        foreach ($report['uptime_logs'] as $log) {
            $rows[] = "uptime,{$log->checked_at},{$log->status},{$log->status_code},{$log->response_time_ms}";
        }

        return implode("\n", $rows)."\n";
    }
}
