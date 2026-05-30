@extends('layouts.app')
@section('title', 'Dashboard')
@section('content')

{{-- Metric cards --}}
<div class="grid gap-4 md:grid-cols-3 xl:grid-cols-6" id="dashboard-metrics">
    <x-metric-card title="Total Sites"   :value="$metrics['total']" />
    <x-metric-card title="Sites Up"      :value="$metrics['up']"           color="green" />
    <x-metric-card title="Sites Down"    :value="$metrics['down']"         color="red" />
    <x-metric-card title="Degraded"      :value="$metrics['degraded']"     color="yellow" />
    <x-metric-card title="Avg SEO"       :value="$metrics['avg_seo'] ?: 'N/A'" color="blue" />
    <x-metric-card title="SSL Expiring"  :value="$metrics['ssl_expiring']" color="yellow" />
</div>

{{-- Fleet chart --}}
<section class="mt-6 bg-white border border-slate-200 rounded-lg p-6 shadow-sm">
    <h2 class="text-lg font-semibold mb-4 text-slate-900">Fleet Response Time — Last 60 Minutes</h2>
    <x-response-time-chart :sites="auth()->user()->sites" />
</section>

{{-- Recent failures --}}
<section class="mt-6 bg-white border border-slate-200 rounded-lg p-6 shadow-sm">
    <div class="flex items-center justify-between mb-4">
        <h2 class="text-lg font-semibold text-slate-900">Recent Failed Checks</h2>
        <span id="failures-updated" class="text-xs text-slate-400"></span>
    </div>
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="border-b">
                <tr class="text-left">
                    <th class="py-2 font-medium">Site</th>
                    <th class="py-2 font-medium">Status</th>
                    <th class="py-2 font-medium">Code</th>
                    <th class="py-2 font-medium">Time</th>
                </tr>
            </thead>
            <tbody id="failures-tbody">
                @forelse ($recentFailures as $log)
                    <tr class="border-t hover:bg-slate-50">
                        <td class="py-2">{{ $log->site->name ?: parse_url($log->site->url, PHP_URL_HOST) }}</td>
                        <td class="py-2"><x-status-badge :status="$log->status" /></td>
                        <td class="py-2">{{ $log->status_code }}</td>
                        <td class="py-2 text-slate-600">{{ $log->checked_at->diffForHumans() }}</td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="py-4 text-center text-slate-600">No recent failures</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</section>

<script>
const metricsUrl  = '{{ route('dashboard.metrics') }}';
const failuresUrl = '{{ route('dashboard.failures') }}';

const statusColors = {
    up: 'bg-emerald-100 text-emerald-800',
    down: 'bg-red-100 text-red-800',
    degraded: 'bg-amber-100 text-amber-800',
    timeout: 'bg-orange-100 text-orange-800',
};

async function refreshMetrics() {
    try {
        const res  = await fetch(metricsUrl);
        const data = await res.json();
        const cards = document.querySelectorAll('#dashboard-metrics [data-metric]');
        const map = {
            'Total Sites':  data.total,
            'Sites Up':     data.up,
            'Sites Down':   data.down,
            'Degraded':     data.degraded,
            'Avg SEO':      data.avg_seo || 'N/A',
            'SSL Expiring': data.ssl_expiring,
        };
        cards.forEach(card => {
            const title = card.querySelector('[data-title]')?.textContent?.trim();
            const valEl = card.querySelector('[data-value]');
            if (title && valEl && map[title] !== undefined) {
                valEl.textContent = map[title];
            }
        });
    } catch (e) { /* silent */ }
}

async function refreshFailures() {
    try {
        const res  = await fetch(failuresUrl);
        const rows = await res.json();
        const tbody = document.getElementById('failures-tbody');
        if (!tbody) return;

        if (!rows.length) {
            tbody.innerHTML = '<tr><td colspan="4" class="py-4 text-center text-slate-600">No recent failures</td></tr>';
        } else {
            tbody.innerHTML = rows.map(r => {
                const colorClass = statusColors[r.status] || 'bg-slate-100 text-slate-700';
                return `<tr class="border-t hover:bg-slate-50">
                    <td class="py-2">${r.site}</td>
                    <td class="py-2"><span class="px-2 py-0.5 rounded text-xs font-semibold ${colorClass}">${r.status}</span></td>
                    <td class="py-2">${r.status_code ?? '—'}</td>
                    <td class="py-2 text-slate-600">${r.checked_at}</td>
                </tr>`;
            }).join('');
        }

        const el = document.getElementById('failures-updated');
        if (el) el.textContent = 'Updated ' + new Date().toLocaleTimeString();
    } catch (e) { /* silent */ }
}

// Refresh metrics + failures every 30s — chart handles its own 60s polling
setInterval(() => { refreshMetrics(); refreshFailures(); }, 30000);
</script>
@endsection
