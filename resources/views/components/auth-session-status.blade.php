@props(['status'])

@if ($status)
    <div {{ $attributes->merge(['class' => 'rounded-lg border border-bull/30 bg-bull/10 px-4 py-3 text-sm text-bull-400']) }}>
        {{ $status }}
    </div>
@endif
