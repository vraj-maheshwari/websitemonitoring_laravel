<h3 class="mt-6 mb-2 font-semibold">Recent Uptime Logs</h3>
<div class="overflow-x-auto">
<table class="w-full text-sm">
<thead class="text-left text-xs uppercase text-slate-500"><tr><th class="py-2">Checked</th><th>Status</th><th>Code</th><th>Response</th><th>TTFB</th><th>Error</th></tr></thead>
<tbody>
@foreach ($site->uptimeLogs as $log)
    <tr class="border-t"><td class="py-2">{{ $log->checked_at }}</td><td><x-status-badge :status="$log->status" /></td><td>{{ $log->status_code ?: 'N/A' }}</td><td>{{ $log->response_time_ms }} ms</td><td>{{ $log->ttfb_ms }} ms</td><td class="max-w-md truncate" title="{{ $log->error_message }}">{{ $log->error_message ?: '-' }}</td></tr>
@endforeach
</tbody></table>
</div>
