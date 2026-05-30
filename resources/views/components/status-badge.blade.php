@props(['status'])
@php
    $value = strtolower((string) $status);
    $class = match (true) {
        in_array($value, ['up','ok','valid','good','resolved','done'], true) => 'bg-emerald-100 text-emerald-800',
        in_array($value, ['down','error','expired','critical','poor','failed','open'], true) => 'bg-red-100 text-red-800',
        in_array($value, ['degraded','warning','expiring','queued','running','processing'], true) => 'bg-amber-100 text-amber-800',
        default => 'bg-slate-100 text-slate-700',
    };
@endphp
<span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium {{ $class }}">{{ $status ?: 'unknown' }}</span>
