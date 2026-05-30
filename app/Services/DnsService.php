<?php

namespace App\Services;

use App\Models\DnsLog;
use App\Models\Site;
use Illuminate\Support\Facades\Log;

class DnsService
{
    public function __construct(private MonitoringService $monitoring, private AlertService $alerts) {}

    public function resolveDns(string $hostname): array
    {
        $a = dns_get_record($hostname, DNS_A + DNS_AAAA) ?: [];
        $ns = dns_get_record($hostname, DNS_NS) ?: [];
        $mx = dns_get_record($hostname, DNS_MX) ?: [];

        return [
            'resolved' => count($a) > 0,
            'ips' => collect($a)->map(fn ($r) => $r['ip'] ?? $r['ipv6'] ?? null)->filter()->values()->all(),
            'nameservers' => collect($ns)->pluck('target')->filter()->values()->all(),
            'mx_records' => collect($mx)->pluck('target')->filter()->values()->all(),
            'error_message' => count($a) ? null : 'No A or AAAA records resolved.',
        ];
    }

    public function detectDnsHijacking(string $hostname, array $expectedIps): bool
    {
        $current = $this->resolveDns($hostname)['ips'];
        return $expectedIps !== [] && array_values(array_diff($current, $expectedIps)) !== [];
    }

    public function detectNameserverChanges(string $hostname, array $expectedNs): bool
    {
        $current = $this->resolveDns($hostname)['nameservers'];
        sort($current);
        sort($expectedNs);
        return $expectedNs !== [] && $current !== $expectedNs;
    }

    public function runDnsCheck(Site $site): DnsLog
    {
        $result = $this->resolveDns(parse_url($site->url, PHP_URL_HOST));
        $previous = $site->dnsLogs()->latest('checked_at')->first();
        $result['hijack_suspected'] = $previous ? array_values(array_diff($result['ips'], $previous->ips ?: [])) !== [] : false;
        $result['ns_changed'] = $previous ? array_values(array_diff($result['nameservers'], $previous->nameservers ?: [])) !== [] : false;

        return $this->applyDnsCheckResult($site, $result, now());
    }

    public function applyDnsCheckResult(Site $site, array $result, \Illuminate\Support\Carbon $checkedAt): DnsLog
    {
        $log = DnsLog::create([
            'site_id' => $site->id,
            'checked_at' => $checkedAt,
            'resolved' => $result['resolved'],
            'ips' => $result['ips'],
            'nameservers' => $result['nameservers'],
            'mx_records' => $result['mx_records'],
            'hijack_suspected' => $result['hijack_suspected'],
            'ns_changed' => $result['ns_changed'],
            'error_message' => $result['error_message'],
        ]);

        Log::info('DNS check completed', [
            'site_id' => $site->id,
            'url' => $site->url,
            'resolved' => $result['resolved'],
            'ips' => $result['ips'],
            'nameservers' => $result['nameservers'],
            'mx_records' => $result['mx_records'],
            'hijack_suspected' => $result['hijack_suspected'],
            'ns_changed' => $result['ns_changed'],
            'error' => $result['error_message'],
        ]);

        $site->fill([
            'dns_resolved' => $result['resolved'],
            'dns_last_ips' => $result['ips'],
            'dns_last_ns' => $result['nameservers'],
            'dns_hijack_suspected' => $result['hijack_suspected'],
            'dns_ns_changed' => $result['ns_changed'],
            'dns_status' => $result['resolved'] && ! $result['hijack_suspected'] && ! $result['ns_changed'] ? 'ok' : 'warning',
        ])->save();

        $this->monitoring->scheduleNextRun($site, 'dns', $checkedAt);
        $this->alerts->checkDnsAlerts($site->fresh());

        return $log;
    }
}
