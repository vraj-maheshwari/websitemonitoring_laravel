@csrf
<div class="grid gap-4 md:grid-cols-2">
    <label class="block text-sm">URL <input name="url" type="url" value="{{ old('url', $site->url) }}" class="mt-1 w-full rounded-md border p-2" required></label>
    <label class="block text-sm">Display Name <input name="name" value="{{ old('name', $site->name) }}" class="mt-1 w-full rounded-md border p-2"></label>
    <label class="block text-sm md:col-span-2">Tracked Keywords <input name="tracked_keywords" value="{{ old('tracked_keywords', implode(', ', $site->tracked_keywords ?: [])) }}" class="mt-1 w-full rounded-md border p-2"></label>
    @foreach (['uptime' => 300, 'ssl' => 86400, 'seo' => 86400, 'security' => 86400, 'dns' => 86400] as $type => $default)
        <label class="block text-sm">{{ ucfirst($type) }} interval seconds <input name="{{ $type }}_interval" type="number" min="{{ $type === 'uptime' ? 60 : 3600 }}" value="{{ old($type.'_interval', $site->{$type.'_interval'} ?: $default) }}" class="mt-1 w-full rounded-md border p-2" required></label>
    @endforeach
</div>
<button class="mt-5 rounded-md bg-slate-900 px-4 py-2 text-white">Save Monitor</button>
