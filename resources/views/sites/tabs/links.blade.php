<div class="flex gap-2">
    <form method="POST" action="{{ route('sites.broken-links.recheck', $site) }}">@csrf <button class="rounded-md border px-3 py-2 text-sm">Recheck Broken Links</button></form>
    <form method="POST" action="{{ route('sites.full-link-audits.store', $site) }}">@csrf <button class="rounded-md bg-slate-900 px-3 py-2 text-sm text-white">Start Full Link Audit</button></form>
</div>

@php
    $results = $latestAudit?->results ?: [];
    $summary = $results['summary'] ?? [];
    $links = collect($results['links'] ?? []);
    $pages = collect($results['pages'] ?? []);
@endphp

<div class="mt-4 grid gap-4 md:grid-cols-4">
    <x-metric-card title="Pages Crawled" :value="$pages->count()" />
    <x-metric-card title="Links Checked" :value="$links->count()" />
    <x-metric-card title="Broken" :value="$summary['broken'] ?? $links->where('state', 'broken')->count()" color="red" />
    <x-metric-card title="Unverified" :value="$summary['unverified'] ?? $links->where('state', 'unverified')->count()" color="yellow" />
</div>

<div class="mt-4 overflow-x-auto rounded-lg border border-slate-200 bg-white">
    <table class="w-full text-sm">
        <thead class="bg-slate-50 text-left text-xs uppercase text-slate-500">
            <tr>
                <th class="px-4 py-3">URL</th>
                <th class="px-4 py-3">Status</th>
                <th class="px-4 py-3">State</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($links as $link)
                @php
                    $state = $link['state'] ?? 'unknown';
                    $stateClass = $state === 'ok' ? 'bg-emerald-100 text-emerald-700' : ($state === 'broken' ? 'bg-red-100 text-red-700' : 'bg-amber-100 text-amber-700');
                @endphp
                <tr class="border-t hover:bg-slate-50">
                    <td class="max-w-xl break-all px-4 py-3 text-slate-700">{{ $link['url'] ?? '-' }}</td>
                    <td class="px-4 py-3">{{ $link['status'] ?? '-' }}</td>
                    <td class="px-4 py-3"><span class="rounded px-2 py-0.5 text-xs font-semibold {{ $stateClass }}">{{ ucfirst($state) }}</span></td>
                </tr>
            @empty
                <tr>
                    <td colspan="3" class="px-4 py-6 text-center text-slate-500">No link audit results available yet.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>
