@extends('layouts.app')
@section('title', 'Security')
@section('content')
<div class="overflow-x-auto bg-white border rounded-lg">
    <table class="w-full text-sm">
        <thead class="bg-slate-100 text-left"><tr><th class="p-3">Site</th><th>Grade</th><th>SSL</th><th>DNS Hijack</th><th>NS Changed</th></tr></thead>
        <tbody>
        @foreach ($sites as $site)
            <tr class="border-t"><td class="p-3"><a class="text-blue-700" href="{{ route('sites.show', $site) }}">{{ $site->name }}</a></td><td>{{ $site->security_grade ?: 'N/A' }}</td><td>{{ $site->ssl_state }} {{ $site->ssl_days_remaining }}</td><td>{{ $site->dns_hijack_suspected ? 'Yes' : 'No' }}</td><td>{{ $site->dns_ns_changed ? 'Yes' : 'No' }}</td></tr>
        @endforeach
        </tbody>
    </table>
</div>
@endsection
