<?php

namespace App\Services;

use App\Models\DailySeoSummary;
use App\Models\DailySslSummary;
use App\Models\DailyUptimeSummary;
use App\Models\Site;
use Illuminate\Support\Carbon;

class SummaryService
{
    public function runDailySummary(?Carbon $targetDate = null): void
    {
        $date = ($targetDate ?: now()->subDay())->toDateString();

        Site::query()->each(function (Site $site) use ($date) {
            $uptime = $site->uptimeLogs()->whereDate('checked_at', $date)->get();
            DailyUptimeSummary::updateOrCreate(['site_id' => $site->id, 'date' => $date], [
                'total_checks' => $uptime->count(),
                'up_count' => $uptime->where('status', 'up')->count(),
                'down_count' => $uptime->where('status', 'down')->count(),
                'degraded_count' => $uptime->where('status', 'degraded')->count(),
                'avg_response_time_ms' => $uptime->avg('response_time_ms'),
                'uptime_percent' => $uptime->count() ? round(($uptime->where('is_up', true)->count() / $uptime->count()) * 100, 2) : null,
            ]);

            $ssl = $site->sslLogs()->whereDate('checked_at', $date)->latest('checked_at')->first();
            if ($ssl) {
                DailySslSummary::updateOrCreate(['site_id' => $site->id, 'date' => $date], ['ssl_state' => $ssl->ssl_state, 'days_remaining' => $ssl->days_remaining]);
            }

            $seo = $site->seoLogs()->whereDate('checked_at', $date)->latest('checked_at')->first();
            if ($seo) {
                DailySeoSummary::updateOrCreate(['site_id' => $site->id, 'date' => $date], ['score' => $seo->score, 'state' => $seo->state]);
            }
        });
    }
}
