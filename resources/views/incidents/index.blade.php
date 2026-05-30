@extends('layouts.app')
@section('title', 'Incidents')
@section('content')
<div class="mb-4 flex gap-2">
    <a class="rounded-md border px-3 py-2 text-sm" href="{{ route('incidents.index') }}">All</a>
    <a class="rounded-md border px-3 py-2 text-sm" href="{{ route('incidents.index', ['status' => 'open']) }}">Open</a>
    <a class="rounded-md border px-3 py-2 text-sm" href="{{ route('incidents.index', ['status' => 'resolved']) }}">Resolved</a>
</div>
<div class="overflow-x-auto bg-white border rounded-lg">
    <table class="w-full text-sm">
        <thead class="bg-slate-100 text-left"><tr><th class="p-3">Site</th><th>Root Cause</th><th>Status</th><th>Opened</th><th>Resolved</th></tr></thead>
        <tbody>
        @foreach ($incidents as $incident)
            <tr class="border-t"><td class="p-3">{{ $incident->site->name }}</td><td>{{ $incident->root_cause }}</td><td><x-status-badge :status="$incident->status" /></td><td>{{ $incident->opened_at }}</td><td>{{ $incident->resolved_at ?: 'N/A' }}</td></tr>
        @endforeach
        </tbody>
    </table>
</div>
{{ $incidents->links() }}
@endsection
