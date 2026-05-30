<div class="grid gap-4 md:grid-cols-4">
    <x-metric-card title="SSL State" :value="$site->ssl_state" />
    <x-metric-card title="Issuer" :value="$site->ssl_issuer ?: 'N/A'" />
    <x-metric-card title="Expiry" :value="optional($site->ssl_expiry_date)->toDateString() ?: 'N/A'" />
    <x-metric-card title="Days Remaining" :value="$site->ssl_days_remaining ?? 'N/A'" />
</div>
<h3 class="mt-6 mb-2 font-semibold">SSL Check Logs</h3>
<div class="overflow-x-auto">
    <table class="w-full text-sm">
        <thead class="text-left text-xs uppercase text-slate-500"><tr><th class="py-2">Checked</th><th>State</th><th>Issuer</th><th>Expiry</th><th>Days</th><th>Error</th></tr></thead>
        <tbody>
        @foreach ($site->sslLogs as $log)
            <tr class="border-t"><td class="py-2">{{ $log->checked_at }}</td><td><x-status-badge :status="$log->ssl_state" /></td><td>{{ $log->issuer ?: 'N/A' }}</td><td>{{ optional($log->expiry_date)->toDateString() ?: 'N/A' }}</td><td>{{ $log->days_remaining ?? 'N/A' }}</td><td class="max-w-md truncate" title="{{ $log->error_message }}">{{ $log->error_message ?: '-' }}</td></tr>
        @endforeach
        </tbody>
    </table>
</div>
