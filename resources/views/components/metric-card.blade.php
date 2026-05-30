@props(['title', 'value', 'color' => 'slate', 'icon' => null])
@php($classes = ['green' => 'text-emerald-700', 'red' => 'text-red-700', 'yellow' => 'text-amber-700', 'blue' => 'text-blue-700', 'gray' => 'text-slate-700', 'slate' => 'text-slate-700'][$color] ?? 'text-slate-700')
<div class="bg-white border border-slate-200 rounded-lg p-4" data-metric>
    <p class="text-sm text-slate-500" data-title>{{ $title }}</p>
    <p class="mt-2 text-2xl font-bold {{ $classes }}" data-value>{{ $value }}</p>
</div>
