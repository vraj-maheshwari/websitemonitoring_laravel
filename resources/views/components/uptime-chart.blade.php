@props(['siteId'])
<canvas id="uptime-chart-{{ $siteId }}" class="block h-64 max-h-64 w-full"></canvas>
<script>
const uptimeUrl{{ $siteId }} = '{{ route('api.sites.history.uptime', $siteId) }}';
let uptimeChart{{ $siteId }} = null;
async function fetchUptimeAndRender{{ $siteId }}() {
    try {
        const res = await fetch(uptimeUrl{{ $siteId }});
        const rows = await res.json();
        const container = document.getElementById('uptime-chart-{{ $siteId }}').parentElement;
        if (!Array.isArray(rows) || rows.length === 0) {
            container.innerHTML = '<div class="p-6 text-sm text-slate-600">No uptime history available.</div>';
            return;
        }
        const labels = rows.map(r => r.checked_at);
        const dataPoints = rows.map(r => r.response_time_ms);
        const ctx = document.getElementById('uptime-chart-{{ $siteId }}');
        if (!uptimeChart{{ $siteId }}) {
            uptimeChart{{ $siteId }} = new Chart(ctx, { type: 'line', data: { labels, datasets: [{ label: 'Response ms', data: dataPoints, borderColor: '#0f766e', tension: .25 }] }, options: { responsive: true, maintainAspectRatio: false, scales: { x: { ticks: { maxTicksLimit: 8 } } } } });
        } else {
            uptimeChart{{ $siteId }}.data.labels = labels;
            uptimeChart{{ $siteId }}.data.datasets[0].data = dataPoints;
            uptimeChart{{ $siteId }}.update();
        }
    } catch (e) {
        console.error('Failed to fetch uptime history', e);
    }
}

fetchUptimeAndRender{{ $siteId }}();
setInterval(fetchUptimeAndRender{{ $siteId }}, 60000);
</script>
