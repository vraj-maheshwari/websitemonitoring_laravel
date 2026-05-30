@php
    $latestDns = $site->dnsLogs->first();
    $ips = collect($latestDns?->ips ?: $site->dns_last_ips ?: []);
    $nameservers = collect($latestDns?->nameservers ?: $site->dns_last_ns ?: []);
    $mxRecords = collect($latestDns?->mx_records ?: []);
    $txtRecords = collect($latestDns?->txt_records ?: []);
    $cnameRecords = collect($latestDns?->cname_records ?: []);
    $soaRecords = collect($latestDns?->soa_records ?: []);
    $caaRecords = collect($latestDns?->caa_records ?: []);
    $nameserverHealth = collect($latestDns?->nameserver_health ?: []);
    $changeDetails = $latestDns?->change_details ?: [];
    $risk = $site->dns_hijack_risk ?? $latestDns?->hijack_risk ?? 'none';
    $dnssec = $site->dnssec_enabled ?? $latestDns?->dnssec_enabled;
    $ttlWarnings = collect([
        ($latestDns?->ttl_min !== null && $latestDns->ttl_min < 60) ? 'Very low TTL detected' : null,
        ($latestDns?->ttl_max !== null && $latestDns->ttl_max > 86400) ? 'Very high TTL detected' : null,
    ])->filter();
    $alerts = [
        ['label' => 'Unresolved', 'active' => ! $site->dns_resolved, 'level' => 'red'],
        ['label' => 'DNSSEC disabled', 'active' => $dnssec === false && $site->dns_resolved, 'level' => 'yellow'],
        ['label' => 'High hijack risk', 'active' => in_array($risk, ['high', 'critical'], true), 'level' => 'red'],
        ['label' => 'Nameserver changed', 'active' => $site->dns_ns_changed, 'level' => 'yellow'],
        ['label' => 'MX changed', 'active' => ($changeDetails['mx_records']['changed'] ?? false) === true, 'level' => 'yellow'],
        ['label' => 'CAA missing', 'active' => $latestDns && $caaRecords->isEmpty(), 'level' => 'yellow'],
        ['label' => 'Slow DNS', 'active' => ($site->dns_response_time_ms ?? $latestDns?->response_time_ms ?? 0) > 500, 'level' => 'yellow'],
        ['label' => 'Low score', 'active' => ($site->dns_score ?? $latestDns?->dns_score ?? 100) < 50, 'level' => 'red'],
    ];
@endphp

<div class="grid gap-4 md:grid-cols-4">
    <x-metric-card title="DNS Status" :value="$site->dns_resolved ? 'Resolved' : 'Unresolved'" />
    <x-metric-card title="DNS Score" :value="$site->dns_score ?? $latestDns?->dns_score ?? 'N/A'" />
    <x-metric-card title="DNS Grade" :value="$site->dns_grade ?? $latestDns?->dns_grade ?? 'N/A'" />
    <x-metric-card title="Response Time" :value="($site->dns_response_time_ms ?? $latestDns?->response_time_ms) !== null ? (($site->dns_response_time_ms ?? $latestDns?->response_time_ms).' ms') : 'N/A'" />
</div>

<div class="mt-6 grid gap-4 xl:grid-cols-2">
    <section class="rounded-lg border border-slate-200 bg-white p-5">
        <h3 class="font-semibold text-slate-800">DNS Overview</h3>
        <dl class="mt-4 grid gap-3 text-sm md:grid-cols-2">
            <div><dt class="text-slate-500">Status</dt><dd class="mt-1"><x-status-badge :status="$site->dns_resolved ? 'resolved' : 'error'" /></dd></div>
            <div><dt class="text-slate-500">DNSSEC</dt><dd class="mt-1 text-slate-800">{{ $dnssec === null ? 'N/A' : ($dnssec ? 'Enabled' : 'Disabled') }}</dd></div>
            <div><dt class="text-slate-500">Hijack risk</dt><dd class="mt-1 text-slate-800">{{ ucfirst($risk) }}</dd></div>
            <div><dt class="text-slate-500">Nameserver changed</dt><dd class="mt-1 text-slate-800">{{ $site->dns_ns_changed ? 'Yes' : 'No' }}</dd></div>
            <div><dt class="text-slate-500">Score</dt><dd class="mt-1 text-slate-800">{{ $site->dns_score ?? $latestDns?->dns_score ?? 'N/A' }}</dd></div>
            <div><dt class="text-slate-500">Grade</dt><dd class="mt-1 text-slate-800">{{ $site->dns_grade ?? $latestDns?->dns_grade ?? 'N/A' }}</dd></div>
        </dl>
        @if ($site->dns_error_details || $latestDns?->dns_error_details || $latestDns?->error_message)
            <div class="mt-4 rounded-md border border-red-100 bg-red-50 p-3 text-sm text-red-800">{{ $site->dns_error_details ?? $latestDns?->dns_error_details ?? $latestDns?->error_message }}</div>
        @endif
    </section>

    <section class="rounded-lg border border-slate-200 bg-white p-5">
        <h3 class="font-semibold text-slate-800">TTL</h3>
        <dl class="mt-4 grid gap-3 text-sm md:grid-cols-3">
            <div class="rounded-md bg-slate-50 p-3"><dt class="text-slate-500">Min TTL</dt><dd class="mt-1 font-semibold text-slate-800">{{ $latestDns?->ttl_min ?? 'N/A' }}</dd></div>
            <div class="rounded-md bg-slate-50 p-3"><dt class="text-slate-500">Max TTL</dt><dd class="mt-1 font-semibold text-slate-800">{{ $latestDns?->ttl_max ?? 'N/A' }}</dd></div>
            <div class="rounded-md bg-slate-50 p-3"><dt class="text-slate-500">Average TTL</dt><dd class="mt-1 font-semibold text-slate-800">{{ $latestDns?->ttl_average ?? 'N/A' }}</dd></div>
        </dl>
        <div class="mt-4 flex flex-wrap gap-2">
            @forelse ($ttlWarnings as $warning)
                <span class="rounded bg-amber-100 px-2.5 py-1 text-xs font-semibold text-amber-700">{{ $warning }}</span>
            @empty
                <span class="rounded bg-emerald-100 px-2.5 py-1 text-xs font-semibold text-emerald-700">No TTL warnings</span>
            @endforelse
        </div>
    </section>
</div>

<section class="mt-4 rounded-lg border border-slate-200 bg-white p-5">
    <h3 class="font-semibold text-slate-800">DNS Records</h3>
    <div class="mt-4 grid gap-4 md:grid-cols-2 xl:grid-cols-3">
        @foreach ([
            'A / AAAA' => $ips,
            'MX' => $mxRecords,
            'TXT' => $txtRecords,
            'CNAME' => $cnameRecords,
            'CAA' => $caaRecords->map(fn ($record) => is_array($record) ? collect($record)->map(fn ($value, $key) => "{$key}: {$value}")->implode(', ') : $record),
            'SOA' => $soaRecords->map(fn ($record) => is_array($record) ? collect($record)->map(fn ($value, $key) => "{$key}: {$value}")->implode(', ') : $record),
        ] as $label => $records)
            <div class="rounded-lg border border-slate-200 bg-slate-50 p-4">
                <p class="text-sm font-semibold text-slate-800">{{ $label }}</p>
                <div class="mt-3 flex max-h-36 flex-wrap gap-2 overflow-auto">
                    @forelse ($records as $record)
                        <span class="rounded-md bg-white px-2.5 py-1 font-mono text-xs text-slate-700">{{ $record }}</span>
                    @empty
                        <span class="text-sm text-slate-500">N/A</span>
                    @endforelse
                </div>
            </div>
        @endforeach
    </div>
</section>

<div class="mt-4 grid gap-4 xl:grid-cols-2">
    <section class="rounded-lg border border-slate-200 bg-white p-5">
        <h3 class="font-semibold text-slate-800">Nameservers</h3>
        <div class="mt-3 flex flex-wrap gap-2">
            @forelse ($nameservers as $ns)
                <span class="rounded-md bg-slate-100 px-2.5 py-1 font-mono text-xs text-slate-700">{{ $ns }}</span>
            @empty
                <span class="text-sm text-slate-500">N/A</span>
            @endforelse
        </div>
        <div class="mt-4 overflow-x-auto rounded-lg border border-slate-200">
            <table class="w-full text-sm">
                <thead class="bg-slate-50 text-left text-xs uppercase text-slate-500"><tr><th class="px-3 py-2">Name</th><th>Status</th><th>Latency</th></tr></thead>
                <tbody>
                    @forelse ($nameserverHealth as $health)
                        <tr class="border-t"><td class="px-3 py-2">{{ $health['name'] ?? 'N/A' }}</td><td>{{ $health['status'] ?? 'N/A' }}</td><td>{{ isset($health['response_time']) ? $health['response_time'].' ms' : 'N/A' }}</td></tr>
                    @empty
                        <tr><td colspan="3" class="px-3 py-4 text-center text-slate-500">No nameserver health data.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    <section class="rounded-lg border border-slate-200 bg-white p-5">
        <h3 class="font-semibold text-slate-800">Security Alerts</h3>
        <div class="mt-4 flex flex-wrap gap-2">
            @foreach ($alerts as $alert)
                @php
                    $class = $alert['active']
                        ? ($alert['level'] === 'red' ? 'bg-red-100 text-red-700' : 'bg-amber-100 text-amber-700')
                        : 'bg-emerald-100 text-emerald-700';
                @endphp
                <span class="rounded px-2.5 py-1 text-xs font-semibold {{ $class }}">{{ $alert['label'] }}: {{ $alert['active'] ? 'Yes' : 'No' }}</span>
            @endforeach
        </div>
    </section>
</div>

<h3 class="mt-6 mb-2 font-semibold">DNS Check Logs</h3>
<div class="overflow-x-auto rounded-lg border border-slate-200 bg-white">
    <table class="w-full text-sm">
        <thead class="bg-slate-50 text-left text-xs uppercase text-slate-500"><tr><th class="px-4 py-3">Checked</th><th>Resolved</th><th>Risk</th><th>Score</th><th>Response</th><th>IPs</th><th>Error</th></tr></thead>
        <tbody>
        @foreach ($site->dnsLogs as $log)
            <tr class="border-t">
                <td class="px-4 py-3">{{ $log->checked_at }}</td>
                <td class="px-4 py-3">{{ $log->resolved ? 'yes' : 'no' }}</td>
                <td class="px-4 py-3">{{ $log->hijack_risk ?? 'none' }}</td>
                <td class="px-4 py-3">{{ $log->dns_score ?? 'N/A' }}</td>
                <td class="px-4 py-3">{{ $log->response_time_ms !== null ? $log->response_time_ms.' ms' : 'N/A' }}</td>
                <td class="px-4 py-3">{{ implode(', ', $log->ips ?: []) ?: 'N/A' }}</td>
                <td class="max-w-md truncate px-4 py-3" title="{{ $log->dns_error_details ?? $log->error_message }}">{{ $log->dns_error_details ?? $log->error_message ?? '-' }}</td>
            </tr>
        @endforeach
        </tbody>
    </table>
</div>
