<?php

namespace App\Services;

use App\Models\DailyUptimeSummary;
use App\Models\Site;
use App\Models\UptimeLog;

class AnalyticsService
{
    public function getSiteAnalytics(int $siteId, int $days = 30): array
    {
        $since = now()->subDays($days);
        $logs = UptimeLog::where('site_id', $siteId)->where('checked_at', '>=', $since)->get();
        $responseTimes = $logs->pluck('response_time_ms')->filter()->sort()->values();

        return [
            'uptime_percent' => $logs->count() ? round(($logs->where('is_up', true)->count() / $logs->count()) * 100, 2) : null,
            'avg_response_time' => round((float) $responseTimes->avg(), 2),
            'p95_response_time' => $responseTimes->count() ? $responseTimes[(int) floor(($responseTimes->count() - 1) * 0.95)] : null,
            'incident_count' => Site::findOrFail($siteId)->incidents()->where('opened_at', '>=', $since)->count(),
            'daily' => $this->dailySeriesForSite($siteId, $since),
        ];
    }

    private function dailySeriesForSite(int $siteId, \Carbon\CarbonInterface $since)
    {
        $rows = DailyUptimeSummary::where('site_id', $siteId)->where('date', '>=', $since->toDateString())->orderBy('date')->get(['date', 'uptime_percent', 'avg_response_time_ms']);
        if ($rows->isNotEmpty()) {
            return $rows;
        }

        // Fallback: build daily series from UptimeLog grouped by date
        $logs = UptimeLog::where('site_id', $siteId)->where('checked_at', '>=', $since)->get();
        $grouped = $logs->groupBy(fn ($r) => $r->checked_at->toDateString());
        return collect($grouped)->map(function ($items, $date) {
            $uptimeCount = $items->count();
            $up = $items->where('is_up', true)->count();
            return (object) [
                'date' => $date,
                'uptime_percent' => $uptimeCount ? round(($up / $uptimeCount) * 100, 2) : null,
                'avg_response_time_ms' => round((float) $items->avg('response_time_ms'), 2),
            ];
        })->values();
    }

    public function getFleetAnalytics(int $userId, int $days = 7, ?array $siteIds = null, bool $live = false, int $minutes = 60): array
    {
        // Determine which sites to include
        if ($siteIds && count($siteIds)) {
            $sites = Site::whereIn('id', $siteIds)->where('user_id', $userId)->get();
        } else {
            // only include sites marked as in_fleet; fallback to all if none configured
            $sites = Site::where('user_id', $userId)->where('in_fleet', true)->get();
            if ($sites->isEmpty()) {
                $sites = Site::where('user_id', $userId)->get();
            }
        }

        $series = $sites->map(function ($site) use ($days, $live, $minutes) {
            $data = $live ? $this->minuteSeriesForSite($site->id, $minutes) : $this->dailySeriesForSite($site->id, now()->subDays($days));
            return [
                'site' => $site->name ?: parse_url($site->url, PHP_URL_HOST) ?: $site->url,
                'data' => $data,
            ];
        })->filter(function ($item) {
            // Filter out sites with no data
            return is_countable($item['data']) && count($item['data']) > 0;
        })->values();

        return [
            'site_count' => $sites->count(),
            'average_response_time' => round((float) $sites->avg('last_response_time'), 2),
            'average_ssl_days_remaining' => round((float) $sites->avg('ssl_days_remaining'), 2),
            'incident_count' => $sites->sum(fn ($site) => $site->incidents()->where('opened_at', '>=', now()->subDays($days))->count()),
            'series' => $series,
        ];
    }

    private function minuteSeriesForSite(int $siteId, int $minutes = 60)
    {
        $since = now()->subMinutes($minutes);
        $logs = UptimeLog::where('site_id', $siteId)
            ->where('checked_at', '>=', $since)
            ->orderBy('checked_at')
            ->get();
        
        if ($logs->isEmpty()) {
            return collect();
        }

        // Group by minute precision for better granularity
        $grouped = $logs->groupBy(fn ($r) => $r->checked_at->format('Y-m-d H:i'));
        
        return collect($grouped)->map(function ($items, $minute) {
            $uptimeCount = $items->count();
            $up = $items->where('is_up', true)->count();
            $responseTimes = $items->pluck('response_time_ms')->filter();
            $isDown = $up === 0 && $uptimeCount > 0;

            return (object) [
                'date'               => $minute,
                'uptime_percent'     => $uptimeCount ? round(($up / $uptimeCount) * 100, 2) : null,
                'avg_response_time_ms' => $responseTimes->isNotEmpty() ? round((float) $responseTimes->avg(), 2) : null,
                'is_down'            => $isDown,
            ];
        })->sortKeys()->values();
    }
}
