@props(['label', 'value', 'hint' => null, 'tone' => 'default', 'icon' => null])
@php
    $tones = [
        'default' => 'text-ink-100',
        'gold' => 'text-gold',
        'green' => 'text-bull-400',
        'red' => 'text-bear-400',
    ];
@endphp
<div {{ $attributes->merge(['class' => 'card p-5']) }}>
    <div class="flex items-start justify-between gap-3">
        <div class="text-sm text-ink-400">{{ $label }}</div>
        @if ($icon)
            <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-ink-800 text-ink-400"><x-icon :name="$icon" class="h-4 w-4" /></span>
        @endif
    </div>
    <div class="mt-2 font-display text-2xl font-semibold {{ $tones[$tone] ?? $tones['default'] }}">{{ $value }}</div>
    @if ($hint)
        <div class="mt-1 text-xs text-ink-400">{{ $hint }}</div>
    @endif
</div>
