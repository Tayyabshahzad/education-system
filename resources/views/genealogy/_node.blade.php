@php
    /** @var \App\Models\User $u */
    $u = $node['user'];
    $isFull = (float) $u->paid_amount >= $fullFee;
    $dot = match (true) {
        $u->status === 'blocked' => 'bg-bear',
        $u->status === 'pending' => 'bg-ink-400',
        $isFull => 'bg-gold',
        default => 'bg-bull',
    };
    $href = ($isRoot ?? false) ? null : route($routeName, $u);
@endphp
<li>
    <a @if ($href) href="{{ $href }}" @endif
       @class([
           'relative block w-36 rounded-xl border bg-ink-900 px-3 py-3 text-center transition',
           'border-gold/50 glow-gold' => $isRoot ?? false,
           'border-ink-700 hover:border-gold/40 hover:bg-ink-800' => ! ($isRoot ?? false),
       ])
       title="{{ $u->name }} · paid ${{ number_format((float) $u->paid_amount, 2) }}">
        <span class="relative mx-auto block h-11 w-11">
            <x-avatar :user="$u" size="h-11 w-11" />
            <span class="absolute -bottom-0.5 -right-0.5 h-3 w-3 rounded-full ring-2 ring-ink-900 {{ $dot }}"></span>
        </span>
        <span class="mt-2 block truncate text-sm font-medium text-ink-100">{{ $u->username }}</span>
        @if (($isRoot ?? false) && ! $u->isMember())
            {{-- Teachers, staff and admins pay no fee; show their role instead. --}}
            <span class="block text-[11px] text-gold">{{ \App\Models\User::roleLabel($u->primaryRole()) }}</span>
        @else
            <span class="money block text-[11px] text-ink-400">${{ number_format((float) $u->paid_amount, 0) }} paid</span>
        @endif
        @if ($node['more'])
            <span class="mt-1.5 inline-block rounded-full bg-gold/10 px-2 py-0.5 text-[10px] font-medium text-gold">more below ↓</span>
        @endif
    </a>
    @if ($isAdminView && ($isRoot ?? false) && $u->isMember())
        <a href="{{ route('admin.members.show', $u) }}" class="mt-2 text-xs link">Open profile</a>
    @endif

    @if ($node['children'])
        <ul>
            @foreach ($node['children'] as $child)
                @include('genealogy._node', ['node' => $child, 'isRoot' => false])
            @endforeach
        </ul>
    @endif
</li>
