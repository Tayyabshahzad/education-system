{{-- Vars: $route, $status, $counts --}}
<div class="mb-5 flex flex-wrap gap-2">
    @foreach (['pending' => 'Pending', 'approved' => 'Approved', 'rejected' => 'Rejected', 'all' => 'All'] as $key => $label)
        <a href="{{ route($route, ['status' => $key]) }}"
           @class(['flex items-center gap-2 rounded-lg border px-3.5 py-1.5 text-sm font-medium transition',
                   'border-gold/40 bg-gold/10 text-gold' => $status === $key,
                   'border-ink-700 bg-ink-900 text-ink-400 hover:text-white' => $status !== $key])>
            {{ $label }}
            @if ($key !== 'all')
                <span class="rounded-full bg-ink-800 px-1.5 text-xs text-ink-200">{{ $counts[$key] ?? 0 }}</span>
            @endif
        </a>
    @endforeach
</div>
