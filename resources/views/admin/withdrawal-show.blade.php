<x-app-layout :title="'Withdrawal #'.$withdrawal->id">
    <x-slot:actions>
        <a href="{{ route('admin.withdrawals.index') }}" class="btn btn-secondary btn-sm">Back to requests</a>
    </x-slot:actions>

    @php
        $w = $withdrawal;
        $balanceAfter = (float) $member->wallet_balance;
    @endphp

    <div class="grid gap-6 xl:grid-cols-3">
        {{-- ===== Request + payout details ===== --}}
        <div class="space-y-6 xl:col-span-2">
            <div class="card">
                <div class="card-header">
                    <div>
                        <div class="text-xs text-ink-400">Requested {{ $w->created_at->format('d M Y, h:i A') }} · {{ $w->created_at->diffForHumans() }}</div>
                        <div class="money mt-1 text-3xl font-semibold text-ink-100">${{ number_format((float) $w->amount, 2) }}</div>
                    </div>
                    <x-status-badge :status="$w->status" class="!text-sm" />
                </div>

                <div class="card-body">
                    <h3 class="mb-3 text-xs font-semibold uppercase tracking-wide text-ink-400">Pay to</h3>
                    <dl class="grid gap-3 sm:grid-cols-2">
                        @foreach ([
                            'Method' => $w->method,
                            'Bank / wallet' => $w->bank_name,
                            'Account title' => $w->account_title,
                            'Account number / IBAN' => $w->account_number,
                        ] as $label => $value)
                            <div class="rounded-xl border border-ink-700 bg-ink-800/50 px-4 py-3">
                                <dt class="text-xs text-ink-400">{{ $label }}</dt>
                                <dd class="mt-1 flex items-center justify-between gap-2">
                                    <span class="break-all font-medium text-ink-100 {{ $label === 'Account number / IBAN' ? 'font-mono' : '' }}">{{ $value ?: '—' }}</span>
                                    @if ($value && in_array($label, ['Account title', 'Account number / IBAN'], true))
                                        <button type="button" x-data="{ c: false }" class="shrink-0 text-xs font-semibold text-gold hover:text-gold-400"
                                                @click="(window.isSecureContext && navigator.clipboard ? navigator.clipboard.writeText(@js($value)) : Promise.reject()).catch(() => {}); c = true; setTimeout(() => c = false, 1500)"
                                                x-text="c ? 'Copied' : 'Copy'">Copy</button>
                                    @endif
                                </dd>
                            </div>
                        @endforeach
                    </dl>
                    @if ($w->account_details)
                        <div class="mt-3 rounded-xl border border-ink-700 bg-ink-800/50 px-4 py-3 text-sm">
                            <div class="text-xs text-ink-400">Member's note</div>
                            <p class="mt-1 whitespace-pre-line text-ink-100">{{ $w->account_details }}</p>
                        </div>
                    @endif
                    @if ($savedDiffers)
                        <p class="mt-3 rounded-lg bg-gold/10 px-3 py-2 text-xs text-gold">
                            Heads up: this account number differs from the one saved on the member's profile ({{ $member->account_number }}).
                        </p>
                    @endif
                </div>

                {{-- Actions --}}
                @if ($w->status === 'pending')
                    @can('withdrawals.review')
                        <div class="grid gap-4 border-t border-ink-700 p-5 sm:grid-cols-2" x-data="{ mode: null }">
                            <form method="POST" action="{{ route('admin.withdrawals.approve', $w) }}" enctype="multipart/form-data" class="space-y-3 rounded-xl border border-bull/30 bg-bull/5 p-4">
                                @csrf
                                <div class="text-sm font-semibold text-bull-400">Mark as paid</div>
                                <p class="text-xs text-ink-400">1. Send <span class="money text-ink-100">${{ number_format((float) $w->amount, 2) }}</span> to the account above.<br>2. Attach the bank receipt / screenshot of that transfer.</p>
                                <x-proof-upload label="Transfer receipt (required)" />
                                <x-text-input name="reference" :value="old('reference')" placeholder="Transaction ID (optional)" />
                                <x-text-input name="note" :value="old('note')" placeholder="Note for member (optional)" />
                                <button class="btn btn-success w-full">Approve & mark paid</button>
                            </form>
                            <form method="POST" action="{{ route('admin.withdrawals.reject', $w) }}" class="space-y-2 rounded-xl border border-bear/30 bg-bear/5 p-4">
                                @csrf
                                <div class="text-sm font-semibold text-bear-400">Reject</div>
                                <p class="text-xs text-ink-400">The amount goes back to the member's wallet.</p>
                                <x-text-input name="note" placeholder="Reason (required)" required />
                                <button class="btn btn-danger w-full">Reject request</button>
                            </form>
                        </div>
                    @endcan
                @else
                    <div class="flex flex-wrap items-start gap-5 border-t border-ink-700 px-5 py-4 text-sm text-ink-400">
                        @if ($w->payout_proof_path)
                            <a href="{{ route('withdrawals.receipt', $w) }}" target="_blank" class="group block shrink-0" title="Open full size">
                                <img src="{{ route('withdrawals.receipt', $w) }}" alt="Transfer receipt" class="h-28 w-24 rounded-lg object-cover object-top ring-1 ring-ink-700 transition group-hover:ring-gold">
                            </a>
                        @endif
                        <div class="space-y-1">
                            <div>{{ ucfirst($w->status) }} by <span class="text-ink-100">{{ $w->reviewer?->name ?? '—' }}</span> on {{ $w->reviewed_at?->format('d M Y, h:i A') }}.</div>
                            @if ($w->payout_reference)<div>Transaction ID: <span class="font-mono text-ink-100">{{ $w->payout_reference }}</span></div>@endif
                            @if ($w->note)<div>Note: <span class="text-ink-100">{{ $w->note }}</span></div>@endif
                            @if ($w->payout_proof_path)<a href="{{ route('withdrawals.receipt', $w) }}" target="_blank" class="link text-xs">View transfer receipt</a>@endif
                        </div>
                    </div>
                @endif
            </div>

            {{-- Earnings check --}}
            <div class="grid gap-4 sm:grid-cols-4">
                <x-stat label="Total earned" :value="'$'.number_format($summary['earned'], 2)" tone="green" />
                <x-stat label="Already withdrawn" :value="'$'.number_format($summary['withdrawn'], 2)" />
                <x-stat label="On hold (pending)" :value="'$'.number_format($summary['pending'], 2)" tone="gold" />
                <x-stat label="Wallet now" :value="'$'.number_format($balanceAfter, 2)" />
            </div>

            <div class="card">
                <div class="card-header"><h3 class="card-title">Where the money came from</h3><span class="text-xs text-ink-400">Latest commissions</span></div>
                @if ($commissions->isEmpty())
                    <x-empty icon="chart" title="No commission earned" text="This member has no commission credits. Check wallet adjustments before paying." />
                @else
                    <div class="table-wrap">
                        <table class="table">
                            <thead><tr><th>Date</th><th>From</th><th>Level</th><th class="text-right">Amount</th></tr></thead>
                            <tbody>
                                @foreach ($commissions as $c)
                                    <tr>
                                        <td class="text-ink-400">{{ $c->created_at->format('d M Y') }}</td>
                                        <td>{{ $c->fromUser?->username ?? '—' }}</td>
                                        <td><span class="badge badge-gray">L{{ $c->level }}</span></td>
                                        <td class="money text-right text-bull-400">+${{ number_format((float) $c->amount, 2) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>

            <div class="card">
                <div class="card-header"><h3 class="card-title">Previous withdrawals</h3></div>
                @if ($history->isEmpty())
                    <x-empty icon="arrow-up" title="First withdrawal request" />
                @else
                    <div class="table-wrap">
                        <table class="table">
                            <thead><tr><th>Date</th><th>Amount</th><th>Account</th><th>Status</th><th></th></tr></thead>
                            <tbody>
                                @foreach ($history as $h)
                                    <tr>
                                        <td class="text-ink-400">{{ $h->created_at->format('d M Y') }}</td>
                                        <td class="money">${{ number_format((float) $h->amount, 2) }}</td>
                                        <td class="font-mono text-xs text-ink-400">{{ $h->method }} · {{ $h->account_number ?: '—' }}</td>
                                        <td><x-status-badge :status="$h->status" /></td>
                                        <td class="text-right"><a href="{{ route('admin.withdrawals.show', $h) }}" class="link text-xs">View</a></td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        </div>

        {{-- ===== Member ===== --}}
        <div class="space-y-6">
            <div class="card">
                <div class="card-body">
                    <div class="flex items-center gap-4">
                        <x-avatar :user="$member" size="h-16 w-16" />
                        <div class="min-w-0">
                            <div class="truncate font-display text-lg font-semibold text-ink-100">{{ $member->name }}</div>
                            <div class="text-sm text-ink-400">{{ $member->username }}</div>
                            <div class="mt-1.5 flex flex-wrap gap-1.5">
                                <x-status-badge :status="$member->status" />
                                @if ($member->isFullyPaid())<span class="badge badge-gold">Full member</span>@endif
                            </div>
                        </div>
                    </div>
                    <dl class="mt-5 space-y-2.5 text-sm">
                        @foreach ([
                            'Mobile' => $member->phone,
                            'Email' => $member->email,
                            'CNIC' => $member->cnic,
                            'Country' => $member->country,
                            'Referral code' => $member->referral_code,
                            'Sponsor' => $member->sponsor ? $member->sponsor->name.' ('.$member->sponsor->username.')' : null,
                            'Direct team' => $summary['directs'].' members',
                            'Fee paid' => '$'.number_format($summary['paid'], 2),
                            'Joined' => $member->created_at->format('d M Y'),
                        ] as $label => $value)
                            <div class="flex justify-between gap-4">
                                <dt class="shrink-0 text-ink-400">{{ $label }}</dt>
                                <dd class="truncate text-right text-ink-100 {{ in_array($label, ['CNIC', 'Mobile', 'Referral code'], true) ? 'font-mono' : '' }}" title="{{ $value }}">{{ $value ?: '—' }}</dd>
                            </div>
                        @endforeach
                    </dl>
                    @if ($member->isMember() && auth()->user()->can('members.view'))
                        <a href="{{ route('admin.members.show', $member) }}" class="btn btn-secondary btn-sm mt-5 w-full">Open full member profile</a>
                    @endif
                </div>
            </div>

            <div class="card">
                <div class="card-header"><h3 class="card-title">Saved payout details</h3></div>
                <div class="card-body text-sm">
                    @if ($member->hasPayoutDetails())
                        <dl class="space-y-2.5">
                            @foreach (['Method' => $member->payout_method, 'Bank / wallet' => $member->bank_name, 'Account title' => $member->account_title, 'Account number' => $member->account_number] as $label => $value)
                                <div class="flex justify-between gap-4"><dt class="text-ink-400">{{ $label }}</dt><dd class="break-all text-right text-ink-100">{{ $value ?: '—' }}</dd></div>
                            @endforeach
                        </dl>
                    @else
                        <p class="text-ink-400">The member has not saved default payout details.</p>
                    @endif
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
