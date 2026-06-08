@php
    // Real metrics now come from Site record (lighthouse-derived)
@endphp

<div class="grid gap-4 md:grid-cols-5 mb-6">
    <x-metric-card title="Performance Score" :value="$site->performance_score !== null ? $site->performance_score : 'N/A'" />
    <x-metric-card title="LCP" :value="$site->lcp_ms ? $site->lcp_ms.' ms' : 'N/A'" />
    <x-metric-card title="FCP" :value="$site->fcp_ms ? $site->fcp_ms.' ms' : 'N/A'" />
    <x-metric-card title="TBT" :value="$site->tbt_ms ? $site->tbt_ms.' ms' : 'N/A'" />
    <x-metric-card title="CLS" :value="$site->cls !== null ? $site->cls : 'N/A'" />
</div>

{{-- Resource breakdown --}}
@if($latestSeo?->seo_signals)
    @php $sig = $latestSeo->seo_signals; @endphp
    <h3 class="font-semibold text-slate-800 mb-3">Page Resource Breakdown</h3>
    <div class="grid gap-4 md:grid-cols-4 mb-6">
        <x-metric-card title="Page Size" :value="($sig['page_size_kb'] ?? 'N/A').' KB'" />
        <x-metric-card title="JS Scripts" :value="($sig['js_total'] ?? 'N/A').' total / '.($sig['js_blocking_count'] ?? 'N/A').' blocking'" />
        <x-metric-card title="CSS Sheets" :value="($sig['css_total'] ?? 'N/A').' total / '.($sig['css_blocking_count'] ?? 'N/A').' blocking'" />
        <x-metric-card title="Word Count" :value="$sig['word_count'] ?? 'N/A'" />
    </div>
@endif

@php
    $lighthouse = $latestSeo?->lighthouse ?? null;
    $lighthouseCategories = $lighthouse['categories'] ?? [];
    $lighthouseAudits = collect($lighthouse['audits'] ?? []);
    $failedAudits = $lighthouseAudits->filter(fn ($audit) => isset($audit['score']) && is_numeric($audit['score']) && $audit['score'] < 1 && ($audit['scoreDisplayMode'] ?? '') !== 'notApplicable')
        ->sortBy('score')
        ->take(5);
    $screenshotData = $lighthouse['audits']['final-screenshot']['details']['data'] ?? null;
@endphp

@if ($lighthouse)
    <div class="mb-6 rounded-lg border border-slate-200 bg-white p-4">
        <div class="flex flex-wrap items-center justify-between gap-3 mb-4">
            <div>
                <h3 class="font-semibold text-slate-800">Latest Lighthouse Audit</h3>
                <p class="text-sm text-slate-500">{{ $lighthouse['requestedUrl'] ?? $site->url }}</p>
            </div>
            <div class="text-right text-sm text-slate-500">
                <p>Generated: {{ isset($lighthouse['fetchTime']) ? \Illuminate\Support\Carbon::parse($lighthouse['fetchTime'])->format('d/m/Y H:i') : 'Unknown' }}</p>
                <p>Version: {{ $lighthouse['lighthouseVersion'] ?? 'N/A' }}</p>
            </div>
        </div>

        <div class="grid gap-4 md:grid-cols-4 mb-4">
            @foreach (['performance','accessibility','best-practices','seo'] as $category)
                @php $cat = $lighthouseCategories[$category] ?? null; @endphp
                <div class="rounded-lg border border-slate-200 bg-slate-50 p-4 text-center">
                    <p class="text-xs uppercase tracking-wide text-slate-500">{{ ucwords(str_replace('-', ' ', $category)) }}</p>
                    <p class="mt-2 text-3xl font-semibold text-slate-900">{{ isset($cat['score']) ? round($cat['score'] * 100) : 'N/A' }}</p>
                    <p class="text-xs text-slate-500 mt-1">Score</p>
                </div>
            @endforeach
        </div>

        <div class="grid gap-4 md:grid-cols-4 mb-4">
            @php
                $metrics = [
                    'first-contentful-paint' => 'FCP',
                    'largest-contentful-paint' => 'LCP',
                    'total-blocking-time' => 'TBT',
                    'cumulative-layout-shift' => 'CLS',
                ];
            @endphp
            @foreach ($metrics as $key => $label)
                @php $audit = $lighthouse['audits'][$key] ?? null; @endphp
                <div class="rounded-lg border border-slate-200 bg-white p-4">
                    <p class="text-xs uppercase tracking-wide text-slate-500">{{ $label }}</p>
                    <p class="mt-2 text-2xl font-semibold text-slate-900">{{ $audit['displayValue'] ?? 'N/A' }}</p>
                    <p class="text-xs text-slate-500 mt-1">{{ $audit['title'] ?? '' }}</p>
                </div>
            @endforeach
        </div>

        @if ($failedAudits->isNotEmpty())
            <div class="rounded-lg border border-amber-200 bg-amber-50 p-4 mb-4">
                <h4 class="font-semibold text-amber-800 mb-2">Top Lighthouse Issues</h4>
                <ul class="list-disc pl-5 text-sm text-amber-900 space-y-1">
                    @foreach ($failedAudits as $audit)
                        <li>{{ $audit['title'] ?? 'Unknown issue' }} @if(!empty($audit['displayValue'])) — {{ $audit['displayValue'] }}@endif</li>
                    @endforeach
                </ul>
            </div>
        @endif

        @if ($screenshotData)
            <div class="rounded-lg border border-slate-200 bg-slate-50 p-4">
                <h4 class="font-semibold text-slate-800 mb-3">Final Lighthouse Screenshot</h4>
                <img class="w-full rounded-md border" src="{{ $screenshotData }}" alt="Lighthouse final screenshot" />
            </div>
        @endif
    </div>
@else
    <div class="rounded-lg border border-slate-200 bg-slate-50 p-4">
        <h3 class="font-semibold text-slate-800 mb-2">Lighthouse audit not available</h3>
        <p class="text-sm text-slate-600">
            No Lighthouse result is currently stored for this site. Click <strong>Check Lighthouse</strong> on the site page and wait for the job to finish, or run the audit manually with <code>php artisan site:seo-audit &lt;siteId&gt;</code>.
        </p>
    </div>
@endif
