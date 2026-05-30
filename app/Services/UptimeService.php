<?php

namespace App\Services;

use App\Models\Site;
use App\Models\UptimeLog;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class UptimeService
{
    public function __construct(private MonitoringService $monitoring, private AlertService $alerts) {}

    public function runUptimeCheck(int $siteId): UptimeLog
    {
        $site = Site::findOrFail($siteId);
        $checkedAt = now();
        $previous = $site->current_status;
        $started = microtime(true);
        $statusCode = null;
        $error = null;

        try {
            $response = Http::withHeaders(['User-Agent' => env('HTTP_USER_AGENT', 'WebsiteMonitor/1.0')])
                ->timeout(15)
                ->withoutRedirecting()
                ->head($site->url);

            if ($response->status() === 405) {
                $response = Http::withHeaders(['User-Agent' => env('HTTP_USER_AGENT', 'WebsiteMonitor/1.0')])->timeout(15)->get($site->url);
            }

            $statusCode = $response->status();
        } catch (Throwable $exception) {
            $error = $exception->getMessage();
        }

        $responseMs = round((microtime(true) - $started) * 1000, 2);
        $isUp = $statusCode !== null && $statusCode < 400;
        $status = ! $isUp ? 'down' : ($responseMs > (int) env('RESPONSE_TIME_THRESHOLD', 3000) ? 'degraded' : 'up');

        $log = UptimeLog::create([
            'site_id' => $site->id,
            'checked_at' => $checkedAt,
            'status_code' => $statusCode,
            'response_time_ms' => $responseMs,
            'ttfb_ms' => $responseMs,
            'is_up' => $isUp,
            'status' => $status,
            'error_message' => $error,
        ]);

        Log::info('Uptime check completed', [
            'site_id' => $site->id,
            'url' => $site->url,
            'status' => $status,
            'status_code' => $statusCode,
            'response_time_ms' => $responseMs,
            'ttfb_ms' => $responseMs,
            'error' => $error,
        ]);

        if ($status === 'down' && $previous !== 'down') {
            $site->last_downtime_started_at = $checkedAt;
        }
        if ($previous === 'down' && $status !== 'down') {
            $site->last_downtime_ended_at = $checkedAt;
        }

        $site->fill([
            'current_status' => $status,
            'last_status_code' => $statusCode,
            'last_response_time' => $responseMs,
            'last_ttfb' => $responseMs,
            'last_error_message' => $error,
            'uptime_status' => $status === 'up' ? 'ok' : ($status === 'degraded' ? 'warning' : 'error'),
        ])->save();

        $this->monitoring->scheduleNextRun($site, 'uptime', $checkedAt);
        $this->alerts->checkUptimeAlerts($site->fresh(), $previous, $status);

        return $log;
    }

    public function getUptimeLogs(int $siteId, int $limit = 50)
    {
        return UptimeLog::where('site_id', $siteId)->latest('checked_at')->limit($limit)->get();
    }
}
