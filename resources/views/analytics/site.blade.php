@extends('layouts.app')
@section('title', 'Site Analytics')
@section('content')
<div class="grid gap-4 md:grid-cols-4">
    <x-metric-card title="Uptime" :value="$analytics['uptime_percent'] ? $analytics['uptime_percent'].'%' : 'N/A'" />
    <x-metric-card title="Avg Response" :value="$analytics['avg_response_time'].' ms'" />
    <x-metric-card title="P95 Response" :value="$analytics['p95_response_time'] ? $analytics['p95_response_time'].' ms' : 'N/A'" />
    <x-metric-card title="Incidents" :value="$analytics['incident_count']" />
</div>
@endsection
