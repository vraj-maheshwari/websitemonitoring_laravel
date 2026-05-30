@php
    $cwv = $latestSeo?->cwv_estimate ?? [];
    $lcpS   = $cwv['lcp_estimate_s'] ?? (isset($cwv['lcp_ms']) ? round($cwv['lcp_ms'] / 1000, 2) : null);
    $fidMs  = $cwv['fid_estimate_ms'] ?? $cwv['tbt_ms'] ?? null;
    $cls    = $cwv['cls_estimate'] ?? $cwv['cls'] ?? null;
    $lcpRating = $cwv['lcp_rating'] ?? null;
    $fidRating = $cwv['fid_rating'] ?? null;
    $clsRating = $cwv['cls_rating'] ?? null;
    $ratingColor = fn($r) => match($r) { 'good' => 'text-emerald-600', 'needs_improvement' => 'text-amber-500', default => 'text-red-600' };
@endphp

<div class="grid gap-4 md:grid-cols-5 mb-6">
    <x-metric-card title="Performance Score" :value="$site->performance_score !== null ? $site->performance_score : 'N/A'" />
    <x-metric-card title="LCP" :value="$lcpS ? $lcpS.'s' : ($site->lcp_ms ? $site->lcp_ms.' ms' : 'N/A')" />
    <x-metric-card title="FCP" :value="$site->fcp_ms ? $site->fcp_ms.' ms' : 'N/A'" />
    <x-metric-card title="TBT" :value="$site->tbt_ms ? $site->tbt_ms.' ms' : 'N/A'" />
    <x-metric-card title="CLS" :value="$cls !== null ? $cls : ($site->cls ?? 'N/A')" />
</div>

@if (!empty($cwv))
    <h3 class="font-semibold text-slate-800 mb-3">Heuristic CWV Estimates</h3>
    <div class="grid gap-4 md:grid-cols-3 mb-6">
        {{-- LCP --}}
        <div class="rounded-lg border border-slate-200 bg-white p-4">
            <div class="flex items-center justify-between mb-2">
                <p class="font-semibold text-slate-800">Largest Contentful Paint</p>
                @if($lcpRating)
                    <span class="text-sm font-bold {{ $ratingColor($lcpRating) }}">{{ strtoupper(str_replace('_',' ',$lcpRating)) }}</span>
                @endif
            </div>
            <p class="text-3xl font-bold {{ $lcpRating ? $ratingColor($lcpRating) : '' }}">{{ $lcpS ? $lcpS.'s' : 'N/A' }}</p>
            @if(!empty($cwv['lcp_note']))
                <p class="text-xs text-slate-500 mt-2">{{ $cwv['lcp_note'] }}</p>
            @endif
        </div>
        {{-- FID/INP --}}
        <div class="rounded-lg border border-slate-200 bg-white p-4">
            <div class="flex items-center justify-between mb-2">
                <p class="font-semibold text-slate-800">First Input Delay (est.)</p>
                @if($fidRating)
                    <span class="text-sm font-bold {{ $ratingColor($fidRating) }}">{{ strtoupper(str_replace('_',' ',$fidRating)) }}</span>
                @endif
            </div>
            <p class="text-3xl font-bold {{ $fidRating ? $ratingColor($fidRating) : '' }}">{{ $fidMs !== null ? $fidMs.'ms' : 'N/A' }}</p>
            @if(!empty($cwv['fid_note']))
                <p class="text-xs text-slate-500 mt-2">{{ $cwv['fid_note'] }}</p>
            @endif
        </div>
        {{-- CLS --}}
        <div class="rounded-lg border border-slate-200 bg-white p-4">
            <div class="flex items-center justify-between mb-2">
                <p class="font-semibold text-slate-800">Cumulative Layout Shift</p>
                @if($clsRating)
                    <span class="text-sm font-bold {{ $ratingColor($clsRating) }}">{{ strtoupper(str_replace('_',' ',$clsRating)) }}</span>
                @endif
            </div>
            <p class="text-3xl font-bold {{ $clsRating ? $ratingColor($clsRating) : '' }}">{{ $cls !== null ? $cls : 'N/A' }}</p>
            @if(!empty($cwv['cls_note']))
                <p class="text-xs text-slate-500 mt-2">{{ $cwv['cls_note'] }}</p>
            @endif
        </div>
    </div>
@endif

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
