@extends('layouts.app')
@section('title', $site->name ?: 'Site Detail')
@section('autoRefresh', true)
@section('content')
<div x-data="{ tab: localStorage.getItem('site-{{ $site->id }}-tab') || 'overview' }" x-init="$watch('tab', val => localStorage.setItem('site-{{ $site->id }}-tab', val))" class="space-y-5">
    <div class="bg-white border rounded-lg p-4 flex flex-wrap items-center justify-between gap-3">
        <div><div class="text-sm text-slate-500">{{ $site->url }}</div><x-status-badge :status="$site->app_status" /></div>
        <div class="flex flex-wrap gap-2">
            @foreach (['uptime','ssl','seo','lighthouse','security','dns','ai','all'] as $type)
                @php $label = $type === 'ai' ? 'AI Readiness' : ucfirst($type); @endphp
                <form method="POST" action="{{ route('sites.check', $site) }}">@csrf <input type="hidden" name="type" value="{{ $type }}"><button class="rounded-md border px-3 py-2 text-sm">Check {{ $label }}</button></form>
            @endforeach
        </div>
    </div>
    <div class="flex flex-wrap gap-2">
        @foreach (['overview','ssl','seo','performance','security','tech','links','dns','reports'] as $item)
            <button @click="tab='{{ $item }}'" :class="tab==='{{ $item }}' ? 'bg-slate-900 text-white' : 'bg-white'" class="rounded-md border px-3 py-2 text-sm">{{ ucfirst($item) }}</button>
        @endforeach
    </div>
    <section x-show="tab==='overview'" class="bg-white border rounded-lg p-4">
        <div class="grid gap-4 md:grid-cols-4">
            <x-metric-card title="Current Status" :value="$site->current_status" />
            <x-metric-card title="Response Time" :value="$site->last_response_time ? $site->last_response_time.' ms' : 'N/A'" />
            <x-metric-card title="TTFB" :value="$site->last_ttfb ? $site->last_ttfb.' ms' : 'N/A'" />
            <x-metric-card title="Status Code" :value="$site->last_status_code ?: 'N/A'" />
        </div>
        <div class="mt-6 h-72 max-h-72 overflow-hidden"><x-uptime-chart :site-id="$site->id" /></div>
        @include('sites.tables.uptime')
    </section>
    <section x-show="tab==='ssl'" class="bg-white border rounded-lg p-4">@include('sites.tabs.ssl')</section>
    <section x-show="tab==='seo'" class="bg-white border rounded-lg p-4">@include('sites.tabs.seo')</section>
    <section x-show="tab==='performance'" class="bg-white border rounded-lg p-4">@include('sites.tabs.performance')</section>
    <section x-show="tab==='security'" class="bg-white border rounded-lg p-4">@include('sites.tabs.security')</section>
    <section x-show="tab==='tech'" class="bg-white border rounded-lg p-4">@include('sites.tabs.tech')</section>
    <section x-show="tab==='links'" class="bg-white border rounded-lg p-4">@include('sites.tabs.links')</section>
    <section x-show="tab==='dns'" class="bg-white border rounded-lg p-4">@include('sites.tabs.dns')</section>
    <section x-show="tab==='reports'" class="bg-white border rounded-lg p-4">@include('sites.tabs.reports')</section>
</div>
@endsection
