<x-app-layout :title="$member->name">
    <x-slot:actions>
        @can('members.view')
            <a href="{{ route('admin.genealogy', $member) }}" class="btn btn-secondary btn-sm"><x-icon name="tree" class="h-4 w-4" /> Tree</a>
        @endcan
        @can('members.edit')
            <a href="{{ route('admin.members.edit', $member) }}" class="btn btn-secondary btn-sm">Edit</a>
        @endcan
        @can('members.create')
            <a href="{{ route('admin.users.create', ['role' => 'member', 'sponsor' => $member->id]) }}" class="btn btn-primary btn-sm hidden sm:inline-flex"><x-icon name="user-plus" class="h-4 w-4" /> Add under</a>
        @endcan
    </x-slot:actions>

    <div class="grid gap-6 xl:grid-cols-3">
        {{-- Profile + actions --}}
        <div class="space-y-6">
            <div class="card">
                <div class="card-body">
                    <div class="flex items-center gap-4">
                        <x-avatar :user="$member" size="h-14 w-14" />
                        <div class="min-w-0">
                            <div class="truncate font-display text-lg font-semibold text-ink-100">{{ $member->name }}</div>
                            <div class="text-sm text-ink-400">{{ $member->username }}</div>
                            <div class="mt-1.5 flex gap-1.5">
                                <x-status-badge :status="$member->status" />
                                @if ($member->isFullyPaid())<span class="badge badge-gold">Full</span>@endif
                            </div>
                        </div>
                    </div>
                    <dl class="mt-5 space-y-2.5 text-sm">
                        @foreach ([
                            'Email' => $member->email,
                            'Mobile' => $member->phone,
                            'CNIC' => $member->cnic,
                            'Country' => $member->country,
                            'Referral code' => $member->referral_code,
                            'Sponsor' => $member->sponsor?->username,
                            'Chosen amount' => '$'.number_format((float) $member->joining_amount, 2),
                            'Joined' => $member->created_at->format('d M Y, H:i'),
                            'Batches' => $member->batches->pluck('name')->join(', '),
                            'Payout' => $member->hasPayoutDetails() ? trim($member->payout_method.' · '.$member->bank_name, ' ·') : null,
                            'Account' => $member->hasPayoutDetails() ? $member->account_title.' · '.$member->account_number : null,
                        ] as $label => $value)
                            <div class="flex justify-between gap-4"><dt class="text-ink-400">{{ $label }}</dt><dd class="truncate text-right text-ink-100">{{ $value ?: '—' }}</dd></div>
                        @endforeach
                    </dl>
                </div>
            </div>

            @can('members.status')
                <div class="card">
                    <div class="card-header"><h3 class="card-title">Account status</h3></div>
                    <div class="card-body flex flex-wrap gap-2">
                        @foreach (['active' => 'btn-success', 'blocked' => 'btn-danger', 'pending' => 'btn-secondary'] as $s => $cls)
                            @if ($member->status !== $s)
                                <form method="POST" action="{{ route('admin.members.status', $member) }}">
                                    @csrf @method('PATCH')
                                    <input type="hidden" name="status" value="{{ $s }}">
                                    <button class="btn btn-sm {{ $cls }}">{{ ['active' => 'Activate', 'blocked' => 'Block', 'pending' => 'Set pending'][$s] }}</button>
                                </form>
                            @endif
                        @endforeach
                    </div>
                </div>
            @endcan

            @can('payments.review')
                <div class="card">
                    <div class="card-header"><h3 class="card-title">Record a payment received</h3></div>
                    <div class="card-body">
                        @if ($remaining <= 0)
                            <p class="text-sm text-ink-400">This member has paid the maximum fee (or has a payment awaiting review).</p>
                        @else
                            <form method="POST" action="{{ route('admin.members.payments.store', $member) }}" class="space-y-3">
                                @csrf
                                <div class="relative">
                                    <span class="pointer-events-none absolute inset-y-0 left-3.5 flex items-center text-sm text-ink-400">$</span>
                                    <x-text-input name="amount" type="number" step="0.01" min="{{ $minimum }}" max="{{ $remaining }}" :value="min($remaining, max($fullFee - (float) $member->paid_amount, $minimum))" class="money pl-7" required aria-label="Amount" />
                                </div>
                                <x-text-input name="note" placeholder="Note (e.g. cash, bank ref)" aria-label="Note" />
                                <button class="btn btn-primary w-full btn-sm">Record & approve</button>
                                <p class="field-help">Approved immediately. Upline commission is released if this completes ${{ number_format($fullFee, 0) }}.</p>
                            </form>
                        @endif
                    </div>
                </div>
            @endcan

            @can('members.delete')
                <form method="POST" action="{{ route('admin.members.destroy', $member) }}" onsubmit="return confirm('Delete {{ $member->username }}? Their direct team will move up to their sponsor.')">
                    @csrf @method('DELETE')
                    <button class="btn btn-danger w-full btn-sm">Delete member</button>
                </form>
            @endcan
        </div>

        <div class="space-y-6 xl:col-span-2">
            <div class="grid gap-4 sm:grid-cols-3">
                <x-stat label="Paid" :value="'$'.number_format((float) $member->paid_amount, 2)" :tone="$member->isFullyPaid() ? 'gold' : 'default'" :hint="'Full: $'.number_format($fullFee, 2)" />
                <x-stat label="Wallet" :value="'$'.number_format((float) $member->wallet_balance, 2)" tone="green" />
                <x-stat :label="'Team ('.count($levels).' levels)'" :value="collect($levels)->sum(fn ($l) => $l->count())" :hint="collect($levels)->map(fn ($l, $i) => 'L'.$i.': '.$l->count())->join(' · ')" />
            </div>

            <div class="card">
                <div class="card-header"><h3 class="card-title">Payments</h3></div>
                @if ($payments->isEmpty())
                    <x-empty icon="card" title="No payments" />
                @else
                    <div class="table-wrap">
                        <table class="table">
                            <thead><tr><th>Date</th><th>Amount</th><th>Method</th><th>Status</th><th>Note</th><th></th></tr></thead>
                            <tbody>
                                @foreach ($payments as $p)
                                    <tr>
                                        <td class="text-ink-400">{{ $p->created_at->format('d M Y') }}</td>
                                        <td class="money text-ink-100">${{ number_format((float) $p->amount, 2) }}</td>
                                        <td>{{ $p->methodLabel() }}</td>
                                        <td><x-status-badge :status="$p->status" /></td>
                                        <td class="max-w-[12rem] truncate text-ink-400">{{ $p->note ?: '—' }}</td>
                                        <td class="text-right">@if ($p->proof_path)<a href="{{ route('payments.proof', $p) }}" target="_blank" class="link text-xs">Proof</a>@endif</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>

            <div class="card">
                <div class="card-header"><h3 class="card-title">Wallet activity</h3></div>
                @if ($transactions->isEmpty())
                    <x-empty icon="wallet" title="No wallet activity" />
                @else
                    <div class="table-wrap">
                        <table class="table">
                            <thead><tr><th>Date</th><th>Type</th><th>Details</th><th class="text-right">Amount</th></tr></thead>
                            <tbody>
                                @foreach ($transactions as $tx)
                                    <tr>
                                        <td class="text-ink-400">{{ $tx->created_at->format('d M Y') }}</td>
                                        <td>{{ $tx->typeLabel() }}</td>
                                        <td class="text-ink-400">{{ $tx->description }}</td>
                                        <td class="money text-right {{ $tx->amount >= 0 ? 'text-bull-400' : 'text-bear-400' }}">{{ $tx->amount >= 0 ? '+' : '−' }}${{ number_format(abs((float) $tx->amount), 2) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>

            <div class="card">
                <div class="card-header"><h3 class="card-title">Direct team (level 1)</h3></div>
                @if ($levels[1]->isEmpty())
                    <x-empty icon="users" title="No direct referrals" />
                @else
                    <div class="table-wrap">
                        <table class="table">
                            <tbody>
                                @foreach ($levels[1] as $m)
                                    <tr>
                                        <td><a href="{{ route('admin.members.show', $m) }}" class="font-medium text-ink-100 hover:text-gold">{{ $m->name }}</a><div class="text-xs text-ink-400">{{ $m->username }}</div></td>
                                        <td class="money">${{ number_format((float) $m->paid_amount, 2) }}</td>
                                        <td><x-status-badge :status="$m->status" /></td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        </div>
    </div>
</x-app-layout>
