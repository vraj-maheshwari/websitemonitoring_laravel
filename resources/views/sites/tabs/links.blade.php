<div class="flex gap-2">
    <form method="POST" action="{{ route('sites.broken-links.recheck', $site) }}">@csrf <button class="rounded-md border px-3 py-2 text-sm">Recheck Broken Links</button></form>
    <form method="POST" action="{{ route('sites.full-link-audits.store', $site) }}">@csrf <button class="rounded-md bg-slate-900 px-3 py-2 text-sm text-white">Start Full Link Audit</button></form>
</div>
<pre class="mt-4 overflow-auto rounded-md bg-slate-100 p-3 text-xs">{{ json_encode($latestAudit?->results ?: [], JSON_PRETTY_PRINT) }}</pre>
