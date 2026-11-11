<x-app-layout title="Members">
    <x-slot:actions>
        @can('members.create')
            <a href="{{ route('admin.members.create') }}" class="btn btn-primary btn-sm"><x-icon name="user-plus" class="h-4 w-4" /> Add member</a>
        @endcan
    </x-slot:actions>

    <form method="GET" class="mb-5 grid gap-3 sm:grid-cols-[1fr_auto_auto_auto]">
        <div class="relative">
            <x-icon name="search" class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-ink-400" />
            <x-text-input name="q" :value="$search" placeholder="Search name, username, email, phone or referral code" class="pl-9" />
        </div>
        <select name="status" class="field-select" onchange="this.form.submit()">
            <option value="">All statuses</option>
            @foreach (['pending', 'active', 'blocked'] as $s)
                <option value="{{ $s }}" @selected($status === $s)>{{ ucfirst($s) }}</option>
            @endforeach
        </select>
        <select name="paid" class="field-select" onchange="this.form.submit()">
            <option value="">Any payment</option>
            <option value="full" @selected($paid === 'full')>Full (${{ number_format($fullFee, 0) }}+)</option>
            <option value="partial" @selected($paid === 'partial')>Partial</option>
            <option value="none" @selected($paid === 'none')>Not paid</option>
        </select>
        <button class="btn btn-secondary">Filter</button>
    </form>

    <div class="card">
        @if ($members->isEmpty())
            <x-empty icon="users" title="No members found" />
        @else
            <div class="table-wrap">
                <table class="table">
                    <thead>
                        <tr><th>Member</th><th>Sponsor</th><th>Directs</th><th>Paid</th><th>Wallet</th><th>Status</th><th>Joined</th><th></th></tr>
                    </thead>
                    <tbody>
                        @foreach ($members as $m)
                            <tr>
                                <td>
                                    <a href="{{ route('admin.members.show', $m) }}" class="font-medium text-white hover:text-gold">{{ $m->name }}</a>
                                    <div class="text-xs text-ink-400">{{ $m->username }} · {{ $m->email }}</div>
                                </td>
                                <td class="text-ink-400">{{ $m->sponsor?->username ?? '—' }}</td>
                                <td>{{ $m->referrals_count }}</td>
                                <td class="money {{ (float) $m->paid_amount >= $fullFee ? 'text-gold' : '' }}">${{ number_format((float) $m->paid_amount, 2) }}</td>
                                <td class="money">${{ number_format((float) $m->wallet_balance, 2) }}</td>
                                <td><x-status-badge :status="$m->status" /></td>
                                <td class="text-ink-400">{{ $m->created_at->format('d M Y') }}</td>
                                <td class="text-right"><a href="{{ route('admin.members.show', $m) }}" class="link text-xs">Manage</a></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            {{ $members->links() }}
        @endif
    </div>
</x-app-layout>
