<?php

namespace App\Services;

use App\Models\DnsLog;
use App\Models\Site;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Log;
use Throwable;

class DnsService
{
    public function __construct(private MonitoringService $monitoring, private AlertService $alerts) {}

    public function resolveDns(string $hostname): array
    {
        $start = microtime(true);
        $result = $this->emptyResult();

        try {
            $hostname = trim($hostname);

            if ($hostname === '') {
                throw new \RuntimeException('Hostname is empty.');
            }

            $a = $this->dnsRecords($hostname, DNS_A);
            $aaaa = $this->dnsRecords($hostname, DNS_AAAA);
            $ns = $this->dnsRecords($hostname, DNS_NS);
            $mx = $this->dnsRecords($hostname, DNS_MX);
            $txt = $this->dnsRecords($hostname, DNS_TXT);
            $cname = $this->dnsRecords($hostname, DNS_CNAME);
            $soa = $this->dnsRecords($hostname, DNS_SOA);
            $caa = $this->dnsRecords($hostname, $this->dnsConstant('DNS_CAA'));

            $allRecords = array_merge($a, $aaaa, $ns, $mx, $txt, $cname, $soa, $caa);
            $ttls = collect($allRecords)->pluck('ttl')->filter(fn ($ttl) => is_numeric($ttl))->map(fn ($ttl) => (int) $ttl)->values();
            $ips = collect(array_merge($a, $aaaa))->map(fn ($record) => $record['ip'] ?? $record['ipv6'] ?? null)->filter()->unique()->values()->all();

            $result = array_merge($result, [
                'resolved' => count($ips) > 0,
                'ips' => $ips,
                'nameservers' => collect($ns)->pluck('target')->filter()->unique()->values()->all(),
                'mx_records' => collect($mx)->map(fn ($record) => $record['target'] ?? null)->filter()->unique()->values()->all(),
                'txt_records' => collect($txt)->map(fn ($record) => $record['txt'] ?? implode('', $record['entries'] ?? []))->filter()->unique()->values()->all(),
                'cname_records' => collect($cname)->map(fn ($record) => $record['target'] ?? null)->filter()->unique()->values()->all(),
                'soa_records' => collect($soa)->map(fn ($record) => array_filter([
                    'mname' => $record['mname'] ?? null,
                    'rname' => $record['rname'] ?? null,
                    'serial' => $record['serial'] ?? null,
                    'refresh' => $record['refresh'] ?? null,
                    'retry' => $record['retry'] ?? null,
                    'expire' => $record['expire'] ?? null,
                    'minimum_ttl' => $record['minimum-ttl'] ?? null,
                ], fn ($value) => $value !== null))->values()->all(),
                'caa_records' => collect($caa)->map(fn ($record) => array_filter([
                    'flags' => $record['flags'] ?? null,
                    'tag' => $record['tag'] ?? null,
                    'value' => $record['value'] ?? null,
                ], fn ($value) => $value !== null))->values()->all(),
                'ttl' => $ttls->all(),
                'ttl_min' => $ttls->isNotEmpty() ? $ttls->min() : null,
                'ttl_max' => $ttls->isNotEmpty() ? $ttls->max() : null,
                'ttl_average' => $ttls->isNotEmpty() ? round($ttls->avg(), 2) : null,
                'dnssec_enabled' => $this->hasDnssec($hostname),
                'nameserver_health' => $this->checkNameserverHealth(collect($ns)->pluck('target')->filter()->unique()->values()->all()),
                'error_message' => count($ips) ? null : 'No A or AAAA records resolved.',
            ]);
        } catch (Throwable $exception) {
            $result['error_message'] = $exception->getMessage();
            $result['dns_error_details'] = $exception->getMessage();
        }

        $result['response_time_ms'] = round((microtime(true) - $start) * 1000, 2);

        return $result;
    }

    public function detectDnsHijacking(string $hostname, array $expectedIps): bool
    {
        $current = $this->resolveDns($hostname)['ips'];

        return $expectedIps !== [] && $this->changedValues($expectedIps, $current)['changed'];
    }

    public function detectNameserverChanges(string $hostname, array $expectedNs): bool
    {
        $current = $this->resolveDns($hostname)['nameservers'];

        return $expectedNs !== [] && $this->changedValues($expectedNs, $current)['changed'];
    }

    public function runDnsCheck(Site $site): DnsLog
    {
        $hostname = parse_url($site->url, PHP_URL_HOST) ?: '';
        $result = $this->resolveDns($hostname);
        $previous = $site->dnsLogs()->latest('checked_at')->first();
        $changeDetails = $this->buildChangeDetails($result, $previous);

        $result['change_details'] = $changeDetails;
        $result['hijack_risk'] = $this->calculateHijackRisk($changeDetails);
        $result['hijack_suspected'] = in_array($result['hijack_risk'], ['medium', 'high', 'critical'], true);
        $result['ns_changed'] = $changeDetails['nameservers']['changed'] ?? false;

        [$result['dns_score'], $result['dns_grade']] = $this->scoreDns($result);

        return $this->applyDnsCheckResult($site, $result, now());
    }

    public function applyDnsCheckResult(Site $site, array $result, CarbonInterface  $checkedAt): DnsLog
    {
        $provided = $result;
        $result = array_merge($this->emptyResult(), $result);

        if (! array_key_exists('dns_score', $provided) || ! array_key_exists('dns_grade', $provided)) {
            [$result['dns_score'], $result['dns_grade']] = $this->scoreDns($result);
        }

        Log::info('DNS DEBUG VALUES', [

    'dns_score'=>$result['dns_score'],
    'dns_grade'=>$result['dns_grade'],
    'response_time_ms'=>$result['response_time_ms'],
    'ttl_min'=>$result['ttl_min'],
    'ttl_max'=>$result['ttl_max'],
    'ttl_average'=>$result['ttl_average'],
    'nameservers'=>$result['nameservers'],
    'dnssec_enabled'=>$result['dnssec_enabled']

        ]);

        $log = DnsLog::create([
            'site_id' => $site->id,
            'checked_at' => $checkedAt,
            'resolved' => $result['resolved'],
            'ips' => $result['ips'],
            'nameservers' => $result['nameservers'],
            'mx_records' => $result['mx_records'],
            'txt_records' => $result['txt_records'],
            'cname_records' => $result['cname_records'],
            'soa_records' => $result['soa_records'],
            'caa_records' => $result['caa_records'],
            'dnssec_enabled' => $result['dnssec_enabled'],
            'ttl_min' => $result['ttl_min'],
            'ttl_max' => $result['ttl_max'],
            'ttl_average' => $result['ttl_average'],
            'response_time_ms' => $result['response_time_ms'],
            'hijack_suspected' => $result['hijack_suspected'],
            'hijack_risk' => $result['hijack_risk'],
            'ns_changed' => $result['ns_changed'],
            'dns_score' => $result['dns_score'],
            'dns_grade' => $result['dns_grade'],
            'nameserver_health' => $result['nameserver_health'],
            'change_details' => $result['change_details'],
            'error_message' => $result['error_message'],
            'dns_error_details' => $result['dns_error_details'] ?: $result['error_message'],
        ]);

        Log::info('DNS check completed', [
            'site_id' => $site->id,
            'url' => $site->url,
            'resolved' => $result['resolved'],
            'response_time_ms' => $result['response_time_ms'],
            'dns_score' => $result['dns_score'],
            'dns_grade' => $result['dns_grade'],
            'dnssec_enabled' => $result['dnssec_enabled'],
            'hijack_risk' => $result['hijack_risk'],
            'record_counts' => [
                'ips' => count($result['ips']),
                'nameservers' => count($result['nameservers']),
                'mx' => count($result['mx_records']),
                'txt' => count($result['txt_records']),
                'cname' => count($result['cname_records']),
                'caa' => count($result['caa_records']),
            ],
            'error' => $result['error_message'],
        ]);

        $site->fill([
            'dns_resolved' => $result['resolved'],
            'dns_last_ips' => $result['ips'],
            'dns_last_ns' => $result['nameservers'],
            'dns_hijack_suspected' => $result['hijack_suspected'],
            'dns_ns_changed' => $result['ns_changed'],
            'dns_status' => $result['resolved'] && ! $result['hijack_suspected'] && ! $result['ns_changed'] ? 'ok' : 'warning',
            'dns_score' => $result['dns_score'],
            'dns_grade' => $result['dns_grade'],
            'dns_response_time_ms' => $result['response_time_ms'],
            'dnssec_enabled' => $result['dnssec_enabled'],
            'dns_hijack_risk' => $result['hijack_risk'],
            'dns_error_details' => $result['dns_error_details'] ?: $result['error_message'],
        ])->save();

        $this->monitoring->scheduleNextRun($site, 'dns', $checkedAt);
        $this->alerts->checkDnsAlerts($site->fresh());

        return $log;
    }

    private function emptyResult(): array
    {
        return [
            'resolved' => false,
            'ips' => [],
            'nameservers' => [],
            'mx_records' => [],
            'txt_records' => [],
            'cname_records' => [],
            'soa_records' => [],
            'caa_records' => [],
            'ttl' => [],
            'ttl_min' => null,
            'ttl_max' => null,
            'ttl_average' => null,
            'response_time_ms' => 0,
            'dnssec_enabled' => false,
            'hijack_suspected' => false,
            'hijack_risk' => 'none',
            'ns_changed' => false,
            'dns_score' => 0,
            'dns_grade' => 'F',
            'nameserver_health' => [],
            'change_details' => [],
            'error_message' => null,
            'dns_error_details' => null,
        ];
    }

    private function dnsRecords(string $hostname, int $type): array
    {
        if ($type === 0) {
            return [];
        }

        try {
            return @dns_get_record($hostname, $type) ?: [];
        } catch (Throwable) {
            return [];
        }
    }

    private function dnsConstant(string $name): int
    {
        return defined($name) ? constant($name) : 0;
    }

    private function hasDnssec(string $hostname): bool
    {
        foreach (['DNS_DNSKEY', 'DNS_DS', 'DNS_RRSIG'] as $constant) {
            if ($this->dnsRecords($hostname, $this->dnsConstant($constant)) !== []) {
                return true;
            }
        }

        return false;
    }

    private function checkNameserverHealth(array $nameservers): array
    {
        return collect($nameservers)->map(function (string $nameserver) {
            $start = microtime(true);
            $records = $this->dnsRecords($nameserver, DNS_A + DNS_AAAA);
            $responseTime = round((microtime(true) - $start) * 1000, 2);

            return [
                'name' => $nameserver,
                'status' => $records !== [] ? 'healthy' : 'unavailable',
                'response_time' => $responseTime,
            ];
        })->values()->all();
    }

    private function buildChangeDetails(array $current, ?DnsLog $previous): array
    {
        if (! $previous) {
            return [
                'ips' => ['added' => [], 'removed' => [], 'changed' => false],
                'nameservers' => ['added' => [], 'removed' => [], 'changed' => false],
                'mx_records' => ['added' => [], 'removed' => [], 'changed' => false],
                'cname_records' => ['added' => [], 'removed' => [], 'changed' => false],
            ];
        }

        return [
            'ips' => $this->changedValues($previous->ips ?: [], $current['ips']),
            'nameservers' => $this->changedValues($previous->nameservers ?: [], $current['nameservers']),
            'mx_records' => $this->changedValues($previous->mx_records ?: [], $current['mx_records']),
            'cname_records' => $this->changedValues($previous->cname_records ?: [], $current['cname_records']),
        ];
    }

    private function changedValues(array $expected, array $current): array
    {
        $expected = collect($expected)->filter()->map(fn ($value) => strtolower((string) $value))->unique()->sort()->values()->all();
        $current = collect($current)->filter()->map(fn ($value) => strtolower((string) $value))->unique()->sort()->values()->all();

        $added = array_values(array_diff($current, $expected));
        $removed = array_values(array_diff($expected, $current));

        return [
            'added' => $added,
            'removed' => $removed,
            'changed' => $added !== [] || $removed !== [],
        ];
    }

    private function calculateHijackRisk(array $changes): string
    {
        $ipChanged = $changes['ips']['changed'] ?? false;
        $nsChanged = $changes['nameservers']['changed'] ?? false;
        $mxChanged = $changes['mx_records']['changed'] ?? false;
        $cnameChanged = $changes['cname_records']['changed'] ?? false;
        $allIpsRemoved = $ipChanged && ($changes['ips']['removed'] ?? []) !== [] && ($changes['ips']['added'] ?? []) !== [];

        if ($nsChanged && $mxChanged) {
            return 'critical';
        }
        if ($allIpsRemoved || ($nsChanged && $ipChanged)) {
            return 'high';
        }
        if ($ipChanged && $cnameChanged) {
            return 'medium';
        }
        if ($ipChanged || $mxChanged || $cnameChanged || $nsChanged) {
            return 'low';
        }

        return 'none';
    }

    private function scoreDns(array $result): array
    {
        $score = 100;

        if (! $result['resolved']) {
            $score -= 50;
        }
        if (empty($result['mx_records'])) {
            $score -= 10;
        }
        if (empty($result['caa_records'])) {
            $score -= 10;
        }
        if (! $result['dnssec_enabled']) {
            $score -= 10;
        }
        if (in_array($result['hijack_risk'], ['high', 'critical'], true)) {
            $score -= 30;
        }
        if ($result['ns_changed']) {
            $score -= 20;
        }
        if (($result['response_time_ms'] ?? 0) > 500) {
            $score -= 10;
        }
        if (($result['ttl_min'] ?? null) !== null && $result['ttl_min'] < 60) {
            $score -= 5;
        }

        $score = max(0, min(100, $score));
        $grade = $score >= 90 ? 'A' : ($score >= 75 ? 'B' : ($score >= 50 ? 'C' : ($score >= 25 ? 'D' : 'F')));

        return [$score, $grade];
    }
}
