<x-app-layout title="Reports" subtitle="Transactions, totals and month-end records. Every total below matches the records listed underneath it.">
    @php
        $range = ['from' => $from->format('Y-m-d'), 'to' => $to->format('Y-m-d')];
        $money = fn ($v) => '$'.number_format((float) $v, 2);
    @endphp

    {{-- Period filter --}}
    <div class="card mb-6 p-5">
        <form method="GET" class="flex flex-wrap items-end gap-3">
            <input type="hidden" name="type" value="{{ $type }}">
            <div>
                <x-input-label for="from" value="From" />
                <x-text-input id="from" type="date" name="from" :value="$range['from']" class="mt-1" />
            </div>
            <div>
                <x-input-label for="to" value="To" />
                <x-text-input id="to" type="date" name="to" :value="$range['to']" class="mt-1" />
            </div>
            <button class="btn btn-primary">Show report</button>
        </form>
        <div class="mt-4 flex flex-wrap gap-2">
            @foreach ($presets as $label => [$pFrom, $pTo])
                @php $active = $pFrom->isSameDay($from) && $pTo->isSameDay($to); @endphp
                <a href="{{ route('admin.reports.index', ['from' => $pFrom->format('Y-m-d'), 'to' => $pTo->format('Y-m-d'), 'type' => $type]) }}"
                   @class(['rounded-lg border px-3 py-1.5 text-xs font-medium transition',
                           'border-gold/40 bg-gold/10 text-gold' => $active,
                           'border-ink-700 bg-ink-900 text-ink-400 hover:text-ink-100' => ! $active])>{{ $label }}</a>
            @endforeach
        </div>
    </div>

    <h2 class="mb-4 font-display text-lg font-semibold text-ink-100">
        {{ $from->format('d M Y') }} – {{ $to->format('d M Y') }}
    </h2>

    {{-- Headline totals --}}
    <div class="grid grid-cols-2 gap-3 sm:gap-4 xl:grid-cols-4">
        <x-stat label="Members joined" :value="number_format($summary['members'])" icon="users"
                :hint="$summary['membersActive'].' active · '.$summary['membersFullyPaid'].' fully paid'" />
        <x-stat label="Sales" :value="$money($summary['sales'])" tone="green" icon="card"
                :hint="$summary['salesCount'].' approved '.Str::plural('payment', $summary['salesCount'])" />
        <x-stat label="Commission credited" :value="$money($summary['commission'])" tone="gold" icon="chart"
                :hint="$summary['commissionCount'].' '.Str::plural('commission', $summary['commissionCount'])" />
        <x-stat label="Withdrawals paid" :value="$money($summary['withdrawn'])" icon="arrow-up"
                :hint="$summary['withdrawnCount'].' approved '.Str::plural('request', $summary['withdrawnCount'])" />
    </div>

    {{-- Tally --}}
    <div class="mt-6 grid gap-6 xl:grid-cols-3">
        <div class="card xl:col-span-2">
            <div class="card-header"><h3 class="card-title">Money tally for this period</h3></div>
            <div class="card-body">
                <dl class="space-y-2.5 text-sm">
                    <div class="flex justify-between gap-4"><dt class="text-ink-400">Payment screenshots approved</dt><dd class="money text-ink-100">{{ $money($summary['salesProof']) }}</dd></div>
                    <div class="flex justify-between gap-4"><dt class="text-ink-400">+ Cash / bank recorded by admin</dt><dd class="money text-ink-100">{{ $money($summary['salesAdmin']) }}</dd></div>
                    <div class="flex justify-between gap-4 border-t border-ink-700 pt-2.5 font-medium"><dt class="text-ink-200">= Cash received</dt><dd class="money text-bull-400">{{ $money($summary['cashIn']) }}</dd></div>
                    <div class="flex justify-between gap-4"><dt class="text-ink-400">− Withdrawals paid out</dt><dd class="money text-ink-100">{{ $money($summary['withdrawn']) }}</dd></div>
                    <div class="flex justify-between gap-4 border-t border-ink-700 pt-2.5 text-base font-semibold"><dt class="text-ink-100">= Net cash</dt><dd @class(['money', 'text-bull-400' => $summary['netCash'] >= 0, 'text-bear-400' => $summary['netCash'] < 0])>{{ $money($summary['netCash']) }}</dd></div>
                </dl>
                <div class="mt-5 space-y-1.5 rounded-xl border border-ink-700 bg-ink-800/40 p-4 text-xs text-ink-400">
                    <p>Fees paid from wallets: <span class="money text-ink-200">{{ $money($summary['salesWallet']) }}</span> — counted in sales, but no new cash: it was spent from commission already earned.</p>
                    <p>Commission credited to wallets: <span class="money text-ink-200">{{ $money($summary['commission']) }}</span> — owed to users until they withdraw it.</p>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header"><h3 class="card-title">Right now</h3></div>
            <div class="card-body">
                <dl class="space-y-3 text-sm">
                    <div class="flex justify-between gap-4"><dt class="text-ink-400">Payments awaiting review</dt><dd class="money text-ink-100">{{ $money($summary['pendingPayments']) }}</dd></div>
                    <div class="flex justify-between gap-4"><dt class="text-ink-400">Withdrawals awaiting payout</dt><dd class="money text-ink-100">{{ $money($summary['pendingWithdrawals']) }}</dd></div>
                    <div class="flex justify-between gap-4"><dt class="text-ink-400">Balance held in all wallets</dt><dd class="money text-gold">{{ $money($summary['walletBalances']) }}</dd></div>
                </dl>
                <p class="mt-4 text-xs text-ink-400">Current figures, not limited to the selected dates.</p>
            </div>
        </div>
    </div>

    {{-- Records --}}
    <div class="card mt-6">
        <div class="card-header">
            <div class="flex flex-wrap gap-2" role="tablist">
                @foreach ($types as $key => $label)
                    <a href="{{ route('admin.reports.index', [...$range, 'type' => $key]) }}" role="tab" aria-selected="{{ $type === $key ? 'true' : 'false' }}"
                       @class(['rounded-lg border px-3.5 py-1.5 text-sm font-medium transition',
                               'border-gold/40 bg-gold/10 text-gold' => $type === $key,
                               'border-ink-700 bg-ink-900 text-ink-400 hover:text-ink-100' => $type !== $key])>{{ $label }}</a>
                @endforeach
            </div>
            <a href="{{ route('admin.reports.export', [...$range, 'type' => $type]) }}" class="btn btn-secondary btn-sm">
                <x-icon name="arrow-up" class="h-4 w-4 rotate-180" /> Export CSV ({{ number_format($records->total()) }})
            </a>
        </div>

        @if ($records->isEmpty())
            <x-empty icon="chart" title="No records in this period" text="Try a wider date range." />
        @else
            <div class="table-wrap">
                <table class="table">
                    @if ($type === 'members')
                        <thead><tr><th>Joined</th><th>Member</th><th>Mobile</th><th>CNIC</th><th>Sponsor</th><th>Joining</th><th>Paid</th><th>Status</th></tr></thead>
                        <tbody>
                            @foreach ($records as $m)
                                <tr>
                                    <td class="whitespace-nowrap text-ink-400">{{ $m->created_at->format('d M Y') }}</td>
                                    <td>@can('members.view')<a href="{{ route('admin.members.show', $m) }}" class="font-medium text-ink-100 hover:text-gold">{{ $m->name }}</a>@else<span class="font-medium text-ink-100">{{ $m->name }}</span>@endcan<div class="text-xs text-ink-400">{{ $m->username }} · {{ $m->email }}</div></td>
                                    <td class="font-mono text-xs text-ink-400">{{ $m->phone ?? '—' }}</td>
                                    <td class="font-mono text-xs text-ink-400">{{ $m->cnic ?? '—' }}</td>
                                    <td class="text-ink-400">{{ $m->sponsor?->username ?? '—' }}@if ($m->sponsor && ! $m->sponsor->isMember())<div class="text-xs text-gold">{{ \App\Models\User::roleLabel($m->sponsor->primaryRole()) }}</div>@endif</td>
                                    <td class="money">{{ $money($m->joining_amount) }}</td>
                                    <td class="money">{{ $money($m->paid_amount) }}</td>
                                    <td><x-status-badge :status="$m->status" /></td>
                                </tr>
                            @endforeach
                        </tbody>
                    @elseif ($type === 'payments')
                        <thead><tr><th>Approved</th><th>Member</th><th>Method</th><th>Reference</th><th>Approved by</th><th class="text-right">Amount</th></tr></thead>
                        <tbody>
                            @foreach ($records as $p)
                                <tr>
                                    <td class="whitespace-nowrap text-ink-400">{{ $p->reviewed_at?->format('d M Y, H:i') }}</td>
                                    <td><div class="font-medium text-ink-100">{{ $p->user?->name }}</div><div class="text-xs text-ink-400">{{ $p->user?->username }}@if ($p->payer && $p->payer->id !== $p->user_id) · paid by {{ $p->payer->username }}@endif</div></td>
                                    <td class="text-ink-400">{{ $p->methodLabel() }}</td>
                                    <td class="font-mono text-xs text-ink-400">{{ $p->reference ?? '—' }}</td>
                                    <td class="text-ink-400">{{ $p->reviewer?->name ?? '—' }}</td>
                                    <td class="money text-right text-bull-400">{{ $money($p->amount) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    @elseif ($type === 'commissions')
                        <thead><tr><th>Date</th><th>Earned by</th><th>From member</th><th>Level</th><th class="text-right">Amount</th></tr></thead>
                        <tbody>
                            @foreach ($records as $t)
                                <tr>
                                    <td class="whitespace-nowrap text-ink-400">{{ $t->created_at->format('d M Y, H:i') }}</td>
                                    <td><div class="font-medium text-ink-100">{{ $t->user?->name }}</div><div class="text-xs text-ink-400">{{ $t->user?->username }}</div></td>
                                    <td class="text-ink-400">{{ $t->fromUser?->username ?? '—' }}</td>
                                    <td class="text-ink-400">{{ $t->level ? 'Level '.$t->level : '—' }}</td>
                                    <td class="money text-right text-gold">{{ $money($t->amount) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    @else
                        <thead><tr><th>Paid</th><th>Member</th><th>Paid to</th><th>Transfer ref.</th><th>Approved by</th><th class="text-right">Amount</th></tr></thead>
                        <tbody>
                            @foreach ($records as $w)
                                <tr>
                                    <td class="whitespace-nowrap text-ink-400">{{ $w->reviewed_at?->format('d M Y, H:i') }}</td>
                                    <td>@can('withdrawals.view')<a href="{{ route('admin.withdrawals.show', $w) }}" class="font-medium text-ink-100 hover:text-gold">{{ $w->user?->name }}</a>@else<span class="font-medium text-ink-100">{{ $w->user?->name }}</span>@endcan<div class="text-xs text-ink-400">{{ $w->user?->username }}</div></td>
                                    <td class="text-ink-400">{{ $w->method }}<div class="font-mono text-xs">{{ $w->account_title }} · {{ $w->account_number }}</div></td>
                                    <td class="font-mono text-xs text-ink-400">{{ $w->payout_reference ?? '—' }}</td>
                                    <td class="text-ink-400">{{ $w->reviewer?->name ?? '—' }}</td>
                                    <td class="money text-right">{{ $money($w->amount) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    @endif
                </table>
            </div>
            <div class="border-t border-ink-700 px-5 py-4">{{ $records->links() }}</div>
        @endif
    </div>
</x-app-layout>
