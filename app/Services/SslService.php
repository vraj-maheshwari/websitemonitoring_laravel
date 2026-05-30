<?php

namespace App\Services;

use App\Models\Site;
use App\Models\SslLog;
use Illuminate\Support\Facades\Log;
use Throwable;

class SslService
{
    public function __construct(private MonitoringService $monitoring, private AlertService $alerts) {}

    public function runSslCheck(int $siteId): SslLog
    {
        $site = Site::findOrFail($siteId);
        $checkedAt = now();
        $host = parse_url($site->url, PHP_URL_HOST);
        $issuer = null;
        $expiry = null;
        $days = null;
        $state = 'error';
        $error = null;

        try {
            $context = stream_context_create(['ssl' => ['capture_peer_cert' => true, 'verify_peer' => false, 'verify_peer_name' => false]]);
            $client = stream_socket_client("ssl://{$host}:443", $errno, $errstr, 10, STREAM_CLIENT_CONNECT, $context);
            if (! $client) {
                throw new \RuntimeException($errstr ?: 'Unable to connect to SSL endpoint.');
            }
            $params = stream_context_get_params($client);
            $cert = openssl_x509_parse($params['options']['ssl']['peer_certificate']);
            $issuer = $cert['issuer']['O'] ?? $cert['issuer']['CN'] ?? null;
            $expiry = now()->setTimestamp($cert['validTo_time_t'])->startOfDay();
            $days = now()->startOfDay()->diffInDays($expiry, false);
            $state = $days <= 0 ? 'expired' : ($days <= 30 ? 'expiring' : 'valid');
        } catch (Throwable $exception) {
            $error = $exception->getMessage();
        }

        $log = SslLog::create([
            'site_id' => $site->id,
            'checked_at' => $checkedAt,
            'is_valid' => $state === 'valid',
            'issuer' => $issuer,
            'expiry_date' => $expiry,
            'days_remaining' => $days,
            'ssl_state' => $state,
            'error_message' => $error,
        ]);

        Log::info('SSL check completed', [
            'site_id' => $site->id,
            'url' => $site->url,
            'state' => $state,
            'issuer' => $issuer,
            'expiry_date' => optional($expiry)->toDateString(),
            'days_remaining' => $days,
            'error' => $error,
        ]);

        $site->fill([
            'ssl_state' => $state,
            'ssl_issuer' => $issuer,
            'ssl_expiry_date' => $expiry,
            'ssl_days_remaining' => $days,
            'ssl_status' => $state === 'valid' ? 'ok' : ($state === 'expiring' ? 'warning' : 'error'),
        ])->save();

        $this->monitoring->scheduleNextRun($site, 'ssl', $checkedAt);
        $this->alerts->checkSslAlerts($site->fresh());

        return $log;
    }
}
