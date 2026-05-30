@extends('layouts.app')
@section('title', 'Fleet Analytics')
@section('content')
<div class="grid gap-4 md:grid-cols-4">
    <x-metric-card title="Sites" :value="$analytics['site_count']" />
    <x-metric-card title="Avg Response" :value="$analytics['average_response_time'].' ms'" />
    <x-metric-card title="Avg SSL Days" :value="$analytics['average_ssl_days_remaining']" />
    <x-metric-card title="Incidents" :value="$analytics['incident_count']" />
</div>
@endsection
