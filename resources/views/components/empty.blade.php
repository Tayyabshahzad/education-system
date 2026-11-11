@props(['icon' => 'chart', 'title', 'text' => null])
<div {{ $attributes->merge(['class' => 'flex flex-col items-center justify-center px-6 py-12 text-center']) }}>
    <span class="flex h-11 w-11 items-center justify-center rounded-xl border border-ink-700 bg-ink-800 text-ink-400"><x-icon :name="$icon" /></span>
    <p class="mt-3 font-medium text-ink-100">{{ $title }}</p>
    @if ($text)
        <p class="mt-1 max-w-sm text-sm text-ink-400">{{ $text }}</p>
    @endif
    {{ $slot }}
</div>
