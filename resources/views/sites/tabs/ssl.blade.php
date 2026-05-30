@php
    $hasSslData = ! empty($site->ssl_state) && $site->ssl_state !== 'unknown';
    $trustLabel = ! $hasSslData || $site->ssl_is_trusted === null ? 'N/A' : ($site->ssl_is_trusted ? 'Yes' : 'No');
    $hostnameLabel = ! $hasSslData || $site->ssl_hostname_valid === null ? 'N/A' : ($site->ssl_hostname_valid ? 'Yes' : 'No');
    $altNames = collect($site->ssl_subject_alt_names ?: []);
    $sslAlerts = [
        ['label' => 'Expired', 'active' => $site->ssl_state === 'expired', 'level' => 'red'],
        ['label' => 'Critical', 'active' => $site->ssl_state === 'critical', 'level' => 'red'],
        ['label' => 'Weak TLS', 'active' => $hasSslData && $site->ssl_tls_version && ! str_contains(strtolower($site->ssl_tls_version), 'tlsv1.2') && ! str_contains(strtolower($site->ssl_tls_version), 'tlsv1.3'), 'level' => 'yellow'],
        ['label' => 'Hostname mismatch', 'active' => $site->ssl_hostname_valid === false && ! in_array($site->ssl_state, ['unknown', 'error'], true), 'level' => 'red'],
        ['label' => 'Untrusted certificate', 'active' => $site->ssl_is_trusted === false && $site->ssl_state !== 'unknown', 'level' => 'red'],
    ];
@endphp

<div class="grid gap-4 md:grid-cols-4">
    <x-metric-card title="SSL State" :value="$site->ssl_state ?? 'N/A'" />
    <x-metric-card title="TLS Version" :value="$site->ssl_tls_version ?? 'N/A'" />
    <x-metric-card title="SSL Score" :value="$site->ssl_security_score ?? 'N/A'" />
    <x-metric-card title="SSL Grade" :value="$site->ssl_grade ?? 'N/A'" />
</div>

<div class="mt-6 grid gap-4 xl:grid-cols-2">
    <section class="rounded-lg border border-slate-200 bg-white p-5">
        <h3 class="font-semibold text-slate-800">SSL Overview</h3>
        <dl class="mt-4 grid gap-3 text-sm md:grid-cols-2">
            <div><dt class="text-slate-500">Status</dt><dd class="mt-1"><x-status-badge :status="$site->ssl_state ?? 'unknown'" /></dd></div>
            <div><dt class="text-slate-500">Issuer</dt><dd class="mt-1 text-slate-800">{{ $site->ssl_issuer ?? 'N/A' }}</dd></div>
            <div><dt class="text-slate-500">TLS version</dt><dd class="mt-1 text-slate-800">{{ $site->ssl_tls_version ?? 'N/A' }}</dd></div>
            <div><dt class="text-slate-500">Expiry date</dt><dd class="mt-1 text-slate-800">{{ optional($site->ssl_expiry_date)->toDateString() ?? 'N/A' }}</dd></div>
            <div><dt class="text-slate-500">Days remaining</dt><dd class="mt-1 text-slate-800">{{ $site->ssl_days_remaining ?? 'N/A' }}</dd></div>
            <div><dt class="text-slate-500">Hostname validation</dt><dd class="mt-1 text-slate-800">{{ $hostnameLabel }}</dd></div>
            <div><dt class="text-slate-500">Trust status</dt><dd class="mt-1 text-slate-800">{{ $trustLabel }}</dd></div>
            <div><dt class="text-slate-500">Grade</dt><dd class="mt-1 text-slate-800">{{ $site->ssl_grade ?? 'N/A' }}</dd></div>
        </dl>
    </section>

    <section class="rounded-lg border border-slate-200 bg-white p-5">
        <h3 class="font-semibold text-slate-800">Certificate Details</h3>
        <dl class="mt-4 space-y-3 text-sm">
            <div><dt class="text-slate-500">Subject</dt><dd class="mt-1 break-words text-slate-800">{{ $site->ssl_subject ?? 'N/A' }}</dd></div>
            <div><dt class="text-slate-500">Serial Number</dt><dd class="mt-1 break-all font-mono text-xs text-slate-800">{{ $site->ssl_serial_number ?? 'N/A' }}</dd></div>
            <div class="grid gap-3 md:grid-cols-3">
                <div><dt class="text-slate-500">Signature</dt><dd class="mt-1 text-slate-800">{{ $site->ssl_signature_algorithm ?? 'N/A' }}</dd></div>
                <div><dt class="text-slate-500">Version</dt><dd class="mt-1 text-slate-800">{{ $site->ssl_certificate_version ?? 'N/A' }}</dd></div>
                <div><dt class="text-slate-500">Valid from</dt><dd class="mt-1 text-slate-800">{{ optional($site->ssl_valid_from)->toDateString() ?? 'N/A' }}</dd></div>
            </div>
            <div><dt class="text-slate-500">Valid until</dt><dd class="mt-1 text-slate-800">{{ optional($site->ssl_valid_until)->toDateString() ?? 'N/A' }}</dd></div>
            <div>
                <dt class="text-slate-500">Alternative Names</dt>
                <dd class="mt-2 flex max-h-32 flex-wrap gap-2 overflow-auto">
                    @forelse ($altNames as $name)
                        <span class="rounded-md bg-slate-100 px-2.5 py-1 font-mono text-xs text-slate-700">{{ $name }}</span>
                    @empty
                        <span class="text-slate-600">N/A</span>
                    @endforelse
                </dd>
            </div>
        </dl>
    </section>
</div>

<div class="mt-4 grid gap-4 xl:grid-cols-2">
    <section class="rounded-lg border border-slate-200 bg-white p-5">
        <h3 class="font-semibold text-slate-800">Security</h3>
        <div class="mt-4 grid gap-3 text-sm md:grid-cols-2">
            <div class="rounded-md bg-slate-50 p-3"><p class="text-slate-500">Trusted</p><p class="mt-1 font-semibold text-slate-800">{{ $trustLabel }}</p></div>
            <div class="rounded-md bg-slate-50 p-3"><p class="text-slate-500">Hostname Match</p><p class="mt-1 font-semibold text-slate-800">{{ $hostnameLabel }}</p></div>
            <div class="rounded-md bg-slate-50 p-3"><p class="text-slate-500">Security Score</p><p class="mt-1 font-semibold text-slate-800">{{ $site->ssl_security_score ?? 'N/A' }}</p></div>
            <div class="rounded-md bg-slate-50 p-3"><p class="text-slate-500">Grade</p><p class="mt-1 font-semibold text-slate-800">{{ $site->ssl_grade ?? 'N/A' }}</p></div>
        </div>
        @if ($site->ssl_error_details)
            <div class="mt-4 rounded-md border border-red-100 bg-red-50 p-3 text-sm text-red-800">{{ $site->ssl_error_details }}</div>
        @endif
    </section>

    <section class="rounded-lg border border-slate-200 bg-white p-5">
        <h3 class="font-semibold text-slate-800">Alerts</h3>
        <div class="mt-4 flex flex-wrap gap-2">
            @foreach ($sslAlerts as $alert)
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

<h3 class="mt-6 mb-2 font-semibold">SSL Check Logs</h3>
<div class="overflow-x-auto rounded-lg border border-slate-200 bg-white">
    <table class="w-full text-sm">
        <thead class="bg-slate-50 text-left text-xs uppercase text-slate-500">
            <tr><th class="px-4 py-3">Checked</th><th>State</th><th>Issuer</th><th>TLS</th><th>Expiry</th><th>Days</th><th>Score</th><th>Error</th></tr>
        </thead>
        <tbody>
        @foreach ($site->sslLogs as $log)
            <tr class="border-t">
                <td class="px-4 py-3">{{ $log->checked_at }}</td>
                <td class="px-4 py-3"><x-status-badge :status="$log->ssl_state" /></td>
                <td class="px-4 py-3">{{ $log->issuer ?? 'N/A' }}</td>
                <td class="px-4 py-3">{{ $log->ssl_tls_version ?? 'N/A' }}</td>
                <td class="px-4 py-3">{{ optional($log->expiry_date)->toDateString() ?? 'N/A' }}</td>
                <td class="px-4 py-3">{{ $log->days_remaining ?? 'N/A' }}</td>
                <td class="px-4 py-3">{{ $log->ssl_security_score ?? 'N/A' }}</td>
                <td class="max-w-md truncate px-4 py-3" title="{{ $log->ssl_error_details ?? $log->error_message }}">{{ $log->ssl_error_details ?? $log->error_message ?? '-' }}</td>
            </tr>
        @endforeach
        </tbody>
    </table>
</div>
