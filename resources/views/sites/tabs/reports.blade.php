<div class="flex flex-wrap gap-2">
    <a class="rounded-md border px-3 py-2 text-sm" href="{{ route('sites.report', [$site, 'format' => 'json']) }}">JSON report</a>
    <a class="rounded-md border px-3 py-2 text-sm" href="{{ route('sites.report', [$site, 'format' => 'csv']) }}">CSV export</a>
    <a class="rounded-md border px-3 py-2 text-sm" href="{{ route('sites.report', $site) }}">HTML report</a>
</div>
