<div class="grid gap-4 md:grid-cols-3 mb-6">
    <x-metric-card title="SEO Score" :value="$site->seo_score ?? 'N/A'" color="blue" />
    <x-metric-card title="SEO State" :value="$site->seo_state ?? 'N/A'" />
    <x-metric-card title="Tracked Keywords" :value="implode(', ', $site->tracked_keywords ?: []) ?: 'None'" />
</div>

@if ($latestSeo)
    @php
        $sig        = $latestSeo->seo_signals ?? [];
        $brokenLinks = collect($latestSeo->broken_links ?? []);
        $allLinks    = collect($sig['links'] ?? []);
        $uniqueLinks = $allLinks->unique()->values();
        $host        = parse_url($site->url, PHP_URL_HOST);
        $internalUnique = $uniqueLinks->filter(fn($l) => str_contains($l, $host))->count();
        $externalUnique = $uniqueLinks->filter(fn($l) => !str_contains($l, $host))->count();
        $brokenCount = $brokenLinks->where('state', 'broken')->count();
    @endphp

    {{-- Fetch status --}}
    <div class="mb-5 rounded-lg border border-slate-200 bg-white p-4">
        <h3 class="font-semibold text-slate-800 mb-3">Fetch Validation</h3>
        <div class="grid gap-3 md:grid-cols-4 text-sm">
            <div><p class="text-slate-500">Fetch Status</p><p class="font-medium">{{ $latestSeo->fetch_status ?? 'N/A' }}</p></div>
            <div><p class="text-slate-500">Render Mode</p><p class="font-medium">{{ data_get($sig,'_fetch.render_mode','HTTP') }}</p></div>
            <div><p class="text-slate-500">Page Size</p><p class="font-medium">{{ $sig['page_size_kb'] ?? 'N/A' }} KB</p></div>
            <div><p class="text-slate-500">Word Count</p><p class="font-medium">{{ $sig['word_count'] ?? 'N/A' }}</p></div>
        </div>
        @if(data_get($sig,'_fetch.reason_if_empty'))
            <div class="mt-3 rounded-md border border-amber-200 bg-amber-50 p-3 text-sm text-amber-900">{{ data_get($sig,'_fetch.reason_if_empty') }}</div>
        @endif
    </div>

    {{-- Content Audit --}}
    <div class="mb-5 rounded-lg border border-slate-200 bg-white p-4">
        <h3 class="font-semibold text-slate-800 mb-4">Content Audit</h3>
        <div class="grid gap-4 md:grid-cols-2">
            {{-- Left: title/meta/h1 --}}
            <div class="space-y-4">
                <div>
                    <p class="text-xs text-slate-500 uppercase tracking-wide mb-1">Page Title</p>
                    <p class="font-medium text-slate-800">{{ $sig['title'] ?? '—' }}</p>
                    <p class="text-xs text-slate-400 mt-0.5">{{ $sig['title_length'] ?? strlen($sig['title'] ?? '') }} characters
                        @if($sig['title_in_optimal_range'] ?? false) <span class="text-emerald-600">✓ Optimal</span> @endif
                    </p>
                </div>
                <div>
                    <p class="text-xs text-slate-500 uppercase tracking-wide mb-1">Meta Description</p>
                    <p class="font-medium text-slate-800">"{{ $sig['meta_description'] ?? '—' }}"</p>
                    <p class="text-xs text-slate-400 mt-0.5">{{ $sig['meta_description_length'] ?? strlen($sig['meta_description'] ?? '') }} characters
                        @if($sig['meta_description_in_optimal_range'] ?? false) <span class="text-emerald-600">✓ Optimal</span> @endif
                    </p>
                </div>
                <div>
                    <p class="text-xs text-slate-500 uppercase tracking-wide mb-1">H1 Headers
                        <span class="ml-1 font-semibold text-slate-700">({{ $sig['h1_count'] ?? count((array)($sig['h1'] ?? [])) }})</span>
                    </p>
                    @php $h1List = (array)($sig['h1_list'] ?? $sig['h1'] ?? []); @endphp
                    @if(count($h1List) > 1)
                        <div class="space-y-1">
                            @foreach($h1List as $h1)
                                <p class="font-medium text-slate-800 text-sm border-l-2 border-amber-400 pl-2">{{ $h1 }}</p>
                            @endforeach
                        </div>
                        <p class="text-xs text-amber-600 mt-1">⚠ Multiple H1s detected — use exactly one</p>
                    @elseif(count($h1List) === 1)
                        <p class="font-medium text-slate-800">{{ $h1List[0] }}</p>
                    @else
                        <p class="text-slate-400">—</p>
                    @endif
                </div>
            </div>
            {{-- Right: counts grid --}}
            <div class="grid grid-cols-2 gap-3">
                @php
                    $countItems = [
                        'Word Count'       => $sig['word_count'] ?? '—',
                        'H2 Headers'       => $sig['h2_count'] ?? count((array)($sig['h2'] ?? [])),
                        'H3 Headers'       => $sig['h3_count'] ?? '—',
                        'Total Images'     => $sig['img_count'] ?? $sig['images_total'] ?? '—',
                        'Missing Alt Tags' => $sig['missing_alt_count'] ?? $sig['images_without_alt'] ?? '—',
                        'H4 Headers'       => $sig['h4_count'] ?? '—',
                    ];
                @endphp
                @foreach($countItems as $label => $val)
                    <div class="rounded-md bg-slate-50 p-3 text-center">
                        <p class="text-xl font-bold text-slate-800">{{ $val }}</p>
                        <p class="text-xs text-slate-500 mt-0.5">{{ $label }}</p>
                    </div>
                @endforeach
            </div>
        </div>

        {{-- Technical signals --}}
        <div class="mt-5 border-t pt-4">
            <p class="text-xs text-slate-500 uppercase tracking-wide mb-3">Technical Signals</p>
            <div class="grid gap-2 md:grid-cols-4 text-sm">
                @php
                    $techChecks = [
                        'Canonical Tag'  => ['val' => ($sig['has_canonical'] ?? ($sig['canonical'] ?? '') !== ''), 'present' => 'PRESENT', 'missing' => 'MISSING'],
                        'Robots.txt'     => ['val' => $sig['has_robots_txt'] ?? false, 'present' => 'DETECTED', 'missing' => 'NOT FOUND'],
                        'Sitemap.xml'    => ['val' => $sig['has_sitemap_xml'] ?? false, 'present' => 'DETECTED', 'missing' => 'NOT FOUND'],
                        'Language'       => ['val' => $sig['has_lang'] ?? false, 'present' => $sig['html_lang'] ?? 'SET', 'missing' => 'MISSING'],
                        'Favicon'        => ['val' => $sig['has_favicon'] ?? false, 'present' => 'PRESENT', 'missing' => 'MISSING'],
                        'Structured Data'=> ['val' => ($sig['structured_data'] ?? 0) > 0, 'present' => ($sig['structured_data'] ?? 0).' schemas', 'missing' => 'NONE'],
                        'No Noindex'     => ['val' => !($sig['has_noindex'] ?? false), 'present' => 'INDEXABLE', 'missing' => 'NOINDEX SET'],
                        'HTTPS'          => ['val' => $sig['https_redirect'] ?? str_starts_with($site->url, 'https://'), 'present' => 'SECURE', 'missing' => 'INSECURE'],
                    ];
                @endphp
                @foreach($techChecks as $label => $check)
                    <div class="flex items-center gap-2">
                        <span class="px-1.5 py-0.5 rounded text-xs font-semibold {{ $check['val'] ? 'bg-emerald-100 text-emerald-700' : 'bg-red-100 text-red-700' }}">
                            {{ $check['val'] ? $check['present'] : $check['missing'] }}
                        </span>
                        <span class="text-slate-600">{{ $label }}</span>
                    </div>
                @endforeach
            </div>
        </div>
    </div>

    {{-- Recommendations --}}
    @if(!empty($latestSeo->recommendations))
        <div class="mb-5 rounded-lg border border-blue-100 bg-blue-50 p-4">
            <h3 class="font-semibold text-blue-800 mb-2">Top Recommendations</h3>
            <ul class="list-disc pl-5 text-sm text-blue-700 space-y-1">
                @foreach($latestSeo->recommendations as $item)<li>{{ $item }}</li>@endforeach
            </ul>
        </div>
    @endif

    {{-- Keyword density --}}
    <div class="mb-5 rounded-lg border border-slate-200 bg-white p-4">
        <h3 class="font-semibold text-slate-800 mb-3">Tracked Keyword Density</h3>
        @php $kwDensity = $sig['keyword_density'] ?? []; $customKw = $sig['custom_keyword_density'] ?? []; @endphp
        @if(!empty($customKw))
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="border-b"><tr class="text-left text-slate-500"><th class="py-2">Keyword</th><th>Count</th><th>Density</th></tr></thead>
                    <tbody>
                        @foreach($customKw as $kw)
                            <tr class="border-t">
                                <td class="py-2">{{ is_array($kw) ? $kw['keyword'] : $kw }}</td>
                                <td>{{ is_array($kw) ? $kw['count'] : '—' }}</td>
                                <td>{{ is_array($kw) ? $kw['density'] : '—' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @elseif(!empty($kwDensity))
            <p class="text-xs text-slate-500 mb-3">Auto-detected top keywords (no custom keywords configured)</p>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="border-b"><tr class="text-left text-slate-500"><th class="py-2">Keyword</th><th>Count</th><th>Density</th></tr></thead>
                    <tbody>
                        @foreach(array_slice($kwDensity, 0, 10) as $kw)
                            <tr class="border-t">
                                <td class="py-2">{{ is_array($kw) ? $kw['keyword'] : $kw }}</td>
                                <td>{{ is_array($kw) ? $kw['count'] : '—' }}</td>
                                <td>{{ is_array($kw) ? $kw['density'] : '—' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <div class="rounded-md bg-slate-50 border border-slate-200 p-4 text-sm text-slate-600">
                <p class="font-medium mb-1">No Keywords Configured</p>
                <p>Track up to 5 custom keywords to monitor occurrence frequency and density during SEO audits.</p>
                <a href="{{ route('sites.edit', $site) }}" class="mt-2 inline-block text-blue-600 hover:underline text-sm">Configure Keywords →</a>
            </div>
        @endif
    </div>

    {{-- Link Analysis Waterfall --}}
    <div class="mb-5 rounded-lg border border-slate-200 bg-white p-4">
        <h3 class="font-semibold text-slate-800 mb-4">Link Analysis Waterfall</h3>
        <div class="grid gap-3 md:grid-cols-4 mb-4">
            <div class="rounded-md bg-slate-50 p-3 text-center">
                <p class="text-2xl font-bold text-slate-800">{{ $allLinks->count() }}</p>
                <p class="text-xs text-slate-500 mt-0.5">Total in HTML</p>
            </div>
            <div class="rounded-md bg-slate-50 p-3 text-center">
                <p class="text-2xl font-bold text-slate-800">{{ $uniqueLinks->count() }}</p>
                <p class="text-xs text-slate-500 mt-0.5">Unique (Processed)</p>
            </div>
            <div class="rounded-md bg-slate-50 p-3 text-center">
                <p class="text-2xl font-bold text-slate-800">{{ $internalUnique }}</p>
                <p class="text-xs text-slate-500 mt-0.5">Internal (Unique)</p>
            </div>
            <div class="rounded-md bg-slate-50 p-3 text-center">
                <p class="text-2xl font-bold text-slate-800">{{ $externalUnique }}</p>
                <p class="text-xs text-slate-500 mt-0.5">External (Unique)</p>
            </div>
        </div>
        @if($brokenLinks->isNotEmpty())
            <div class="mb-3">
                <p class="text-sm font-semibold text-slate-700 mb-2">
                    Checked Links
                    @if($brokenCount > 0)
                        <span class="ml-2 px-2 py-0.5 rounded text-xs font-semibold bg-red-100 text-red-700">{{ $brokenCount }} Broken</span>
                    @else
                        <span class="ml-2 px-2 py-0.5 rounded text-xs font-semibold bg-emerald-100 text-emerald-700">All OK</span>
                    @endif
                </p>
                <div class="overflow-x-auto">
                    <table class="w-full text-xs">
                        <thead class="border-b text-slate-500"><tr><th class="py-2 text-left">URL</th><th class="py-2">Status</th><th class="py-2">State</th></tr></thead>
                        <tbody>
                            @foreach($brokenLinks as $link)
                                <tr class="border-t {{ $link['state'] === 'broken' ? 'bg-red-50' : '' }}">
                                    <td class="py-1.5 break-all max-w-md">{{ $link['url'] }}</td>
                                    <td class="py-1.5 text-center">{{ $link['status'] ?? '—' }}</td>
                                    <td class="py-1.5 text-center">
                                        <span class="px-1.5 py-0.5 rounded text-xs font-semibold {{ $link['state'] === 'broken' ? 'bg-red-100 text-red-700' : ($link['state'] === 'ok' ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-100 text-slate-600') }}">
                                            {{ strtoupper($link['state']) }}
                                        </span>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endif
    </div>

    {{-- Full signals (collapsible) --}}
    <details class="mb-5">
        <summary class="cursor-pointer text-sm font-semibold text-slate-700 py-2">Full parsed SEO signals</summary>
        <pre class="mt-2 max-h-96 overflow-auto rounded-md bg-slate-100 p-3 text-xs">{{ json_encode(collect($sig)->except(['_fetch', 'links'])->all(), JSON_PRETTY_PRINT) }}</pre>
    </details>
@endif

{{-- SEO check log --}}
<h3 class="font-semibold text-slate-800 mb-2">SEO Check Logs</h3>
<div class="overflow-x-auto rounded-lg border border-slate-200 bg-white">
    <table class="w-full text-sm">
        <thead class="bg-slate-50 border-b text-left text-xs uppercase text-slate-500">
            <tr><th class="px-4 py-2">Checked</th><th class="px-4 py-2">Score</th><th class="px-4 py-2">State</th><th class="px-4 py-2">Fetch</th><th class="px-4 py-2">Issues</th></tr>
        </thead>
        <tbody>
        @foreach ($site->seoLogs as $log)
            <tr class="border-t hover:bg-slate-50">
                <td class="px-4 py-2">{{ $log->checked_at->format('d/m/Y H:i') }}</td>
                <td class="px-4 py-2">{{ $log->score ?? 'N/A' }}</td>
                <td class="px-4 py-2"><x-status-badge :status="$log->state" /></td>
                <td class="px-4 py-2">{{ $log->fetch_status }}</td>
                <td class="px-4 py-2">{{ count($log->issues ?: []) }}</td>
            </tr>
        @endforeach
        </tbody>
    </table>
</div>
