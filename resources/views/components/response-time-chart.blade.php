@props(['sites'])
<div class="mb-4 space-y-3">
    <div class="flex items-center justify-between">
        <h3 class="text-sm font-medium text-slate-700">Select Sites to Monitor</h3>
        <label class="inline-flex items-center text-sm font-medium">
            <input id="fleet-live-toggle" type="checkbox" checked class="mr-2 h-4 w-4 rounded border-slate-300 text-teal-600 focus:ring-teal-500" />
            <span>Live Updates (Last 60 min)</span>
        </label>
    </div>
    <div class="flex flex-wrap gap-3" id="fleet-site-controls">
        @foreach($sites as $s)
            <label class="inline-flex items-center px-3 py-2 bg-slate-50 border border-slate-200 rounded-lg text-sm hover:bg-slate-100 cursor-pointer transition-colors">
                <input type="checkbox" class="fleet-site-toggle mr-2 h-4 w-4 rounded border-slate-300 text-teal-600 focus:ring-teal-500" data-site-id="{{ $s->id }}" {{ $s->in_fleet ? 'checked' : '' }} />
                <span class="font-medium">{{ $s->name ?: parse_url($s->url, PHP_URL_HOST) }}</span>
            </label>
        @endforeach
    </div>
</div>
<div class="bg-white border border-slate-200 rounded-lg p-4">
    <canvas id="fleet-response-chart" class="block w-full" style="height: 500px;"></canvas>
</div>
<script>
const fleetUrl = '{{ route('api.fleet.analytics') }}';
const fleetCheckUrl = '{{ route('api.fleet.check') }}';
let fleetChart = null;
let liveChecksEnabled = true;
let isLoading = false;

document.getElementById('fleet-live-toggle').addEventListener('change', function (e) {
    liveChecksEnabled = e.target.checked;
    fetchFleetAndRender();
});

// Checkbox changes only re-render the chart — no DB persistence
document.querySelectorAll('.fleet-site-toggle').forEach(function (cb) {
    cb.addEventListener('change', function () {
        fetchFleetAndRender();
    });
});

async function fetchFleetAndRender() {
    if (isLoading) return;

    const selected = Array.from(document.querySelectorAll('.fleet-site-toggle:checked'))
        .map(cb => cb.getAttribute('data-site-id'));

    const container = document.getElementById('fleet-response-chart')?.parentElement;

    // Nothing selected — clear chart and show prompt
    if (!selected.length) {
        if (fleetChart) { fleetChart.destroy(); fleetChart = null; }
        if (container) container.innerHTML = '<div class="p-8 text-center text-sm text-slate-500">Select one or more sites above to view their response time.</div>';
        return;
    }

    isLoading = true;
    try {
        if (liveChecksEnabled) {
            try { await fetch(fleetCheckUrl); } catch (e) { /* non-fatal */ }
            await new Promise(r => setTimeout(r, 1500));
        }

        const params = new URLSearchParams();
        params.set('live', liveChecksEnabled ? '1' : '0');
        params.set('minutes', '60');
        params.set('site_ids', selected.join(','));

        const res = await fetch(fleetUrl + '?' + params.toString());
        if (!res.ok) throw new Error('HTTP ' + res.status);

        const data = await res.json();
        const hasSeries = Array.isArray(data.series) && data.series.some(s => Array.isArray(s.data) && s.data.length);

        // Restore canvas if it was replaced by a message div
        if (!document.getElementById('fleet-response-chart')) {
            container.innerHTML = '<canvas id="fleet-response-chart" class="block w-full" style="height: 500px;"></canvas>';
        }

        if (!hasSeries) {
            if (fleetChart) { fleetChart.destroy(); fleetChart = null; }
            container.innerHTML = '<div class="p-8 text-center text-sm text-slate-500">No data yet for the selected sites. Checks are running in the background.</div>';
            return;
        }

        const allDates = new Set();
        data.series.forEach(s => (s.data || []).forEach(d => allDates.add(d.date)));
        const labels = Array.from(allDates).sort();

        const palette = ['#0f766e','#2563eb','#b45309','#6b21a8','#047857','#0891b2','#c026d3'];

        // Find max response time to use as spike height for down periods
        let maxVal = 0;
        data.series.forEach(s => (s.data || []).forEach(d => {
            if (d.avg_response_time_ms > maxVal) maxVal = d.avg_response_time_ms;
        }));
        const spikeVal = maxVal > 0 ? maxVal * 1.15 : 1000; // spike 15% above max

        // Per-site down index sets (for per-site red bands)
        const siteDownIndices = [];
        const datasets = [];

        data.series.forEach((s, i) => {
            const color = palette[i % palette.length];
            const responseMap = {};
            const downMap = {};
            (s.data || []).forEach(d => {
                if (d.avg_response_time_ms != null) responseMap[d.date] = d.avg_response_time_ms;
                if (d.is_down) downMap[d.date] = true;
            });

            const downIndices = new Set(labels.map((l, idx) => downMap[l] ? idx : null).filter(v => v !== null));
            siteDownIndices.push({ color, downIndices });

            datasets.push({
                label: s.site,
                // Down = spike to top, up = normal response time
                data: labels.map(l => downMap[l] ? spikeVal : (responseMap[l] ?? null)),
                borderColor: labels.map(l => downMap[l] ? '#ef4444' : color),
                backgroundColor: color + '18',
                segment: {
                    borderColor: ctx => {
                        // Color each segment red if either endpoint is down
                        const l = labels[ctx.p0DataIndex];
                        const r = labels[ctx.p1DataIndex];
                        return (downMap[l] || downMap[r]) ? '#ef4444' : color;
                    },
                    borderDash: ctx => {
                        const l = labels[ctx.p0DataIndex];
                        return downMap[l] ? [4, 4] : [];
                    }
                },
                pointBackgroundColor: labels.map(l => downMap[l] ? '#ef4444' : color),
                pointBorderColor: labels.map(l => downMap[l] ? '#ef4444' : color),
                pointRadius: labels.map(l => downMap[l] ? 5 : 3),
                pointStyle: labels.map(l => downMap[l] ? 'crossRot' : 'circle'),
                pointHoverRadius: 7,
                borderWidth: 2,
                tension: .3,
                spanGaps: true,
                // Store downMap on dataset for tooltip access
                _downMap: downMap,
            });
        });

        // Custom plugin: full-height colored bands per site for their own down periods
        const downtimeBandPlugin = {
            id: 'downtimeBands',
            beforeDraw(chart) {
                const { ctx, chartArea: { top, bottom }, scales: { x } } = chart;
                if (!x) return;
                
                ctx.save();
                siteDownIndices.forEach(({ color, downIndices }) => {
                    downIndices.forEach(idx => {
                        const xPos = x.getPixelForValue(idx);
                        const halfBand = (x.width / Math.max(labels.length, 1)) * 0.55;
                        // Full-height band
                        ctx.fillStyle = 'rgba(239,68,68,0.12)';
                        ctx.fillRect(xPos - halfBand, top, halfBand * 2, bottom - top);
                        // Bold top cap
                        ctx.fillStyle = 'rgba(239,68,68,0.85)';
                        ctx.fillRect(xPos - halfBand, top, halfBand * 2, 4);
                    });
                });
                ctx.restore();
            }
        };

        const ctx = document.getElementById('fleet-response-chart');
        if (!fleetChart) {
            fleetChart = new Chart(ctx, {
                type: 'line',
                data: { labels, datasets },
                plugins: [downtimeBandPlugin],
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    interaction: { mode: 'index', intersect: false },
                    plugins: {
                        legend: { display: true, position: 'top' },
                        tooltip: {
                            callbacks: {
                                title: items => labels[items[0]?.dataIndex] ?? '',
                                label: c => {
                                    const lbl = labels[c.dataIndex];
                                    const isDown = c.dataset._downMap && c.dataset._downMap[lbl];
                                    if (isDown) return c.dataset.label + ': 🔴 DOWN';
                                    return c.parsed.y != null
                                        ? c.dataset.label + ': ' + c.parsed.y.toFixed(2) + ' ms'
                                        : null; // hide null entries
                                }
                            }
                        }
                    },
                    scales: {
                        x: { title: { display: true, text: 'Time' }, ticks: { maxTicksLimit: 12, maxRotation: 45 } },
                        y: {
                            title: { display: true, text: 'Response Time (ms)' },
                            beginAtZero: true,
                            ticks: {
                                // Hide the spike value from Y axis labels
                                callback: val => val >= spikeVal ? '' : val
                            }
                        }
                    }
                }
            });
        } else {
            fleetChart.config.plugins = [downtimeBandPlugin];
            fleetChart.data.labels = labels;
            fleetChart.data.datasets = datasets;
            fleetChart.update();
        }
    } catch (e) {
        console.error('Fleet analytics error', e);
        if (container && !fleetChart) {
            container.innerHTML = '<div class="p-8 text-center text-sm text-red-600">Failed to load data. Please try again.</div>';
        }
    } finally {
        isLoading = false;
    }
}

fetchFleetAndRender();
setInterval(fetchFleetAndRender, 60000);
</script>
