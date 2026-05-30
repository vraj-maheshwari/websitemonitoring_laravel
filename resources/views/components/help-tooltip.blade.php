@props(['text'])
<span x-data="{ open: false }" class="relative inline-flex">
    <button type="button" @mouseenter="open=true" @mouseleave="open=false" class="text-slate-400">i</button>
    <span x-show="open" class="absolute left-4 top-0 z-10 w-56 rounded-md bg-slate-900 p-2 text-xs text-white">{{ $text }}</span>
</span>
