<x-app-layout title="Admin Dashboard">
    @php $u = auth()->user(); @endphp

    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <x-stat label="Members" :value="number_format($stats['members'])" icon="users" :hint="$stats['active'].' active · '.$stats['pendingMembers'].' pending'" />
        <x-stat label="Full package members" :value="number_format($stats['fullyPaid'])" tone="gold" icon="academic" :hint="$stats['batches'].' class batches'" />
        <x-stat label="Fees collected" :value="'$'.number_format($stats['collected'], 2)" tone="green" icon="card" hint="Approved payments" />
        <x-stat label="Commission paid" :value="'$'.number_format($stats['commissionPaid'], 2)" icon="chart"
                :hint="'$'.number_format($stats['walletLiability'], 2).' in wallets · $'.number_format($stats['withdrawn'], 2).' withdrawn'" />
    </div>

    <div class="mt-6 grid gap-6 xl:grid-cols-2">
        @can('payments.view')
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Payments awaiting review <span class="badge badge-gold ml-1">{{ $pendingPaymentsCount }}</span></h3>
                    <a href="{{ route('admin.payments.index') }}" class="link text-sm">Open queue</a>
                </div>
                @if ($pendingPayments->isEmpty())
                    <x-empty icon="check" title="All caught up" text="No payments waiting for review." />
                @else
                    <div class="table-wrap">
                        <table class="table">
                            <tbody>
                                @foreach ($pendingPayments as $p)
                                    <tr>
                                        <td><div class="font-medium text-white">{{ $p->user->name }}</div><div class="text-xs text-ink-400">{{ $p->user->username }}</div></td>
                                        <td class="money text-white">${{ number_format((float) $p->amount, 2) }}</td>
                                        <td class="text-right text-ink-400">{{ $p->created_at->diffForHumans() }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        @endcan

        @can('withdrawals.view')
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Withdrawal requests <span class="badge badge-gold ml-1">{{ $pendingWithdrawalsCount }}</span></h3>
                    <a href="{{ route('admin.withdrawals.index') }}" class="link text-sm">Open queue</a>
                </div>
                @if ($pendingWithdrawals->isEmpty())
                    <x-empty icon="check" title="All caught up" text="No withdrawal requests pending." />
                @else
                    <div class="table-wrap">
                        <table class="table">
                            <tbody>
                                @foreach ($pendingWithdrawals as $w)
                                    <tr>
                                        <td><div class="font-medium text-white">{{ $w->user->name }}</div><div class="text-xs text-ink-400">{{ $w->method }}</div></td>
                                        <td class="money text-white">${{ number_format((float) $w->amount, 2) }}</td>
                                        <td class="text-right text-ink-400">{{ $w->created_at->diffForHumans() }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        @endcan
    </div>

    @can('members.view')
        <div class="card mt-6">
            <div class="card-header">
                <h3 class="card-title">Newest members</h3>
                <div class="flex gap-2">
                    @can('members.create')
                        <a href="{{ route('admin.users.create') }}" class="btn btn-primary btn-sm"><x-icon name="user-plus" class="h-4 w-4" /> Add user</a>
                    @endcan
                    <a href="{{ route('admin.members.index') }}" class="btn btn-secondary btn-sm">All members</a>
                </div>
            </div>
            <div class="table-wrap">
                <table class="table">
                    <thead><tr><th>Member</th><th>Sponsor</th><th>Paid</th><th>Status</th><th>Joined</th></tr></thead>
                    <tbody>
                        @forelse ($latestMembers as $m)
                            <tr>
                                <td><a href="{{ route('admin.members.show', $m) }}" class="font-medium text-white hover:text-gold">{{ $m->name }}</a><div class="text-xs text-ink-400">{{ $m->username }}</div></td>
                                <td class="text-ink-400">{{ $m->sponsor?->username ?? '—' }}</td>
                                <td class="money">${{ number_format((float) $m->paid_amount, 2) }}</td>
                                <td><x-status-badge :status="$m->status" /></td>
                                <td class="text-ink-400">{{ $m->created_at->format('d M Y') }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="5"><x-empty icon="users" title="No members yet" /></td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    @endcan
</x-app-layout>
