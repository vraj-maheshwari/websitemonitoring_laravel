@php
    $techStack = collect($latestSeo?->tech_stack ?: []);
    $techSections = [
        'cms' => ['label' => 'CMS', 'empty' => 'No CMS detected'],
        'frameworks' => ['label' => 'Frameworks', 'empty' => 'No frontend framework detected'],
        'analytics' => ['label' => 'Analytics', 'empty' => 'No analytics tag detected'],
        'backend' => ['label' => 'Backend', 'empty' => 'No backend technology detected'],
        'infrastructure' => ['label' => 'Infrastructure', 'empty' => 'No infrastructure detected'],
        'server' => ['label' => 'Server', 'empty' => 'Server header not available'],
    ];
@endphp

<div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
    @foreach ($techSections as $key => $section)
        @php
            $items = collect($techStack->get($key, []))->filter()->values();
        @endphp
        <div class="rounded-lg border border-slate-200 bg-white p-4">
            <p class="text-sm font-semibold text-slate-800">{{ $section['label'] }}</p>
            <div class="mt-3 flex flex-wrap gap-2">
                @forelse ($items as $item)
                    <span class="rounded-md bg-slate-100 px-2.5 py-1 text-xs font-medium text-slate-700">{{ $item }}</span>
                @empty
                    <span class="text-sm text-slate-500">{{ $section['empty'] }}</span>
                @endforelse
            </div>
        </div>
    @endforeach
</div>

@if (! $latestSeo)
    <div class="mt-4 rounded-md border border-slate-200 bg-slate-50 p-4 text-sm text-slate-600">
        No technology data available yet. Run an SEO check to populate this section.
    </div>
@else
    <div class="mt-4 rounded-md border border-slate-200 bg-slate-50 p-4 text-sm text-slate-600">
        Technology detection uses the latest SEO check response headers and raw HTML. CMS and frontend frameworks may show as not detected when the site hides build details or renders them only after JavaScript runs.
    </div>
@endif
