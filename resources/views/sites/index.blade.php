@extends('layouts.app')
@section('title', 'Sites')
@section('content')
<div class="mb-4 flex justify-end"><a href="{{ route('sites.create') }}" class="rounded-md bg-slate-900 px-4 py-2 text-white">Add Site</a></div>
<div class="overflow-x-auto bg-white border rounded-lg">
    <table class="w-full text-sm">
        <thead class="bg-slate-100 text-left"><tr><th class="p-3">Site</th><th>Status</th><th>Uptime</th><th>SSL</th><th>SEO</th><th>Last Check</th><th></th></tr></thead>
        <tbody>
        @foreach ($sites as $site)
            <tr class="border-t">
                <td class="p-3"><div class="font-medium">{{ $site->name }}</div><div class="text-xs text-slate-500">{{ $site->url }}</div></td>
                <td><x-status-badge :status="$site->app_status" /></td>
                <td><x-status-badge :status="$site->current_status" /></td>
                <td>{{ $site->ssl_state }} {{ $site->ssl_days_remaining ? "({$site->ssl_days_remaining}d)" : '' }}</td>
                <td>{{ $site->seo_score ?? 'N/A' }}</td>
                <td>{{ optional($site->last_uptime_check_at)->diffForHumans() ?: 'Never' }}</td>
                <td class="p-3 text-right space-x-2">
                    <a href="{{ route('sites.show', $site) }}" class="text-blue-700">View</a>
                    <a href="{{ route('sites.edit', $site) }}" class="text-slate-700">Edit</a>
                    <form method="POST" action="{{ route('sites.destroy', $site) }}" class="inline" onsubmit="return confirm('Delete this monitor?')">@csrf @method('DELETE') <button class="text-red-700">Delete</button></form>
                </td>
            </tr>
        @endforeach
        </tbody>
    </table>
</div>
{{ $sites->links() }}
@endsection
