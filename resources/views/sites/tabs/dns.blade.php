<div class="grid gap-4 md:grid-cols-3">
    <x-metric-card title="Resolved" :value="$site->dns_resolved ? 'yes' : 'no'" />
    <x-metric-card title="Hijack Suspected" :value="$site->dns_hijack_suspected ? 'yes' : 'no'" />
    <x-metric-card title="NS Changed" :value="$site->dns_ns_changed ? 'yes' : 'no'" />
</div>
<pre class="mt-4 overflow-auto rounded-md bg-slate-100 p-3 text-xs">{{ json_encode(['ips' => $site->dns_last_ips, 'nameservers' => $site->dns_last_ns], JSON_PRETTY_PRINT) }}</pre>
<h3 class="mt-6 mb-2 font-semibold">DNS Check Logs</h3>
<div class="overflow-x-auto">
    <table class="w-full text-sm">
        <thead class="text-left text-xs uppercase text-slate-500"><tr><th class="py-2">Checked</th><th>Resolved</th><th>IPs</th><th>Nameservers</th><th>MX</th><th>Error</th></tr></thead>
        <tbody>
        @foreach ($site->dnsLogs as $log)
            <tr class="border-t"><td class="py-2">{{ $log->checked_at }}</td><td>{{ $log->resolved ? 'yes' : 'no' }}</td><td>{{ implode(', ', $log->ips ?: []) }}</td><td>{{ implode(', ', $log->nameservers ?: []) }}</td><td>{{ implode(', ', $log->mx_records ?: []) }}</td><td class="max-w-md truncate" title="{{ $log->error_message }}">{{ $log->error_message ?: '-' }}</td></tr>
        @endforeach
        </tbody>
    </table>
</div>
