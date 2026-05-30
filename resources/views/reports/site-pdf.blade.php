@extends('layouts.app')
@section('title', 'Site Report')
@section('content')
<div class="bg-white border rounded-lg p-6 space-y-5">
    <div>
        <h2 class="text-lg font-semibold">{{ $report['site']['display_name'] }}</h2>
        <p class="text-sm text-slate-500">{{ $report['site']['url'] }}</p>
        <p class="text-xs text-slate-400">Generated {{ $report['generated_at'] }}</p>
    </div>
    <div class="grid gap-4 md:grid-cols-4">
        <x-metric-card title="Status" :value="$report['site']['current_status']" />
        <x-metric-card title="SSL" :value="$report['site']['ssl_state']" />
        <x-metric-card title="SEO" :value="$report['site']['seo_score'] ?? 'N/A'" />
        <x-metric-card title="Security" :value="$report['site']['security_grade'] ?? 'N/A'" />
    </div>
    <pre class="overflow-auto rounded-md bg-slate-100 p-3 text-xs">{{ json_encode($report, JSON_PRETTY_PRINT) }}</pre>
</div>
@endsection
