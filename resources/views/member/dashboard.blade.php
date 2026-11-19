<x-app-layout title="Dashboard">
    @php
        $paid = (float) $user->paid_amount;
        $progress = $fullFee > 0 ? min(100, round($paid / $fullFee * 100)) : 100;
        $teamTotal = collect($levels)->sum(fn ($l) => $l->count());
    @endphp

    {{-- Greeting + account state --}}
    <div class="mb-6 flex flex-wrap items-end justify-between gap-4">
        <div class="flex items-center gap-4">
            <a href="{{ route('profile.edit') }}" title="Change profile picture"><x-avatar :user="$user" size="h-14 w-14" /></a>
            <div>
                <p class="text-sm text-ink-400">Welcome back,</p>
                <h2 class="font-display text-2xl font-semibold text-ink-100">{{ $user->name }}</h2>
            </div>
        </div>
        <div class="flex items-center gap-2">
            <x-status-badge :status="$user->status" />
            @if ($user->isFullyPaid())
                <span class="badge badge-gold">Full member</span>
            @endif
        </div>
    </div>

    {{-- 7x7: commission waiting to be unlocked (always visible, motivates bringing directs) --}}
    @include('partials.waiting-commission-alert', ['refUser' => $user])

    {{-- Payment call-to-action --}}
    @if ($user->status === 'pending' && ! $pendingPayment)
        <div class="mb-6 flex flex-wrap items-center justify-between gap-4 rounded-2xl border border-gold/30 bg-gold/5 p-5">
            <div>
                <p class="font-semibold text-ink-100">Activate your account</p>
                <p class="mt-1 text-sm text-ink-400">Submit your joining payment of <span class="money text-gold">${{ number_format((float) $user->joining_amount, 2) }}</span> to unlock your training.</p>
            </div>
            <a href="{{ route('payments.index') }}" class="btn btn-primary"><x-icon name="upload" class="h-4 w-4" /> Submit payment</a>
        </div>
    @elseif ($pendingPayment)
        <div class="mb-6 flex items-center gap-3 rounded-2xl border border-sky-500/30 bg-sky-500/5 p-5 text-sm">
            <x-icon name="clock" class="text-sky-300" />
            <p class="text-ink-200">Your payment of <span class="money text-ink-100">${{ number_format((float) $pendingPayment->amount, 2) }}</span> is awaiting review. We'll activate it as soon as it's verified.</p>
        </div>
    @elseif (! $user->isFullyPaid())
        <div class="mb-6 flex flex-wrap items-center justify-between gap-4 rounded-2xl border border-ink-700 bg-ink-900 p-5">
            <div>
                <p class="font-semibold text-ink-100">Upgrade to the full package</p>
                <p class="mt-1 text-sm text-ink-400">Pay <span class="money text-gold">${{ number_format($fullFee - $paid, 2) }}</span> more to join live class batches and activate referral earnings for your sponsor.</p>
            </div>
            <a href="{{ route('payments.index') }}" class="btn btn-primary">Top up now</a>
        </div>
    @endif

    {{-- Stats --}}
    <div class="grid grid-cols-2 gap-3 sm:gap-4 xl:grid-cols-4">
        <x-stat label="Wallet balance" :value="'$'.number_format((float) $user->wallet_balance, 2)" tone="gold" icon="wallet"
                :hint="$pendingWithdrawal > 0 ? '$'.number_format($pendingWithdrawal, 2).' on hold for withdrawal' : 'Available to withdraw'" />
        <x-stat label="Total earnings" :value="'$'.number_format($totalEarned, 2)" tone="green" icon="chart" hint="All-time referral commission" />
        <x-stat label="Withdrawn" :value="'$'.number_format($withdrawn, 2)" icon="arrow-up" hint="Paid out to you" />
        <x-stat label="Team size" :value="number_format($teamTotal)" icon="users" :hint="'Across '.count($levels).' levels'" />
    </div>

    <div class="mt-6 grid gap-6 xl:grid-cols-3">
        {{-- Referral link --}}
        @include('partials.referral-card', ['refUser' => $user, 'class' => 'xl:col-span-2'])

        {{-- Fee progress --}}
        <div class="card">
            <div class="card-header"><h3 class="card-title">Your package</h3></div>
            <div class="card-body">
                <div class="flex items-end justify-between">
                    <div>
                        <div class="text-xs text-ink-400">Paid so far</div>
                        <div class="money mt-1 text-2xl font-semibold text-ink-100">${{ number_format($paid, 2) }}</div>
                    </div>
                    <div class="text-right text-xs text-ink-400">of <span class="money">${{ number_format($fullFee, 2) }}</span></div>
                </div>
                <div class="mt-4 h-2 overflow-hidden rounded-full bg-ink-800" role="progressbar" aria-valuenow="{{ $progress }}" aria-valuemin="0" aria-valuemax="100">
                    <div class="h-full rounded-full bg-gradient-to-r from-gold-600 to-gold-400" style="width: {{ $progress }}%"></div>
                </div>
                <div class="mt-5 space-y-3 text-sm">
                    <div class="flex items-center justify-between"><span class="text-ink-400">Sponsor</span><span class="text-ink-100">{{ $user->sponsor?->username ?? '—' }}</span></div>
                    <div class="flex items-center justify-between"><span class="text-ink-400">Joined</span><span class="text-ink-100">{{ $user->created_at->format('d M Y') }}</span></div>
                    <div class="flex items-center justify-between">
                        <span class="text-ink-400">Class batch</span>
                        <span class="text-ink-100">{{ $batches->pluck('name')->join(', ') ?: 'Not assigned yet' }}</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Earnings over time --}}
    @include('partials.metrics-chart', ['chart' => $personalChart, 'title' => 'My performance', 'subtitle' => 'People who joined your '.count($levels).'-level team, commission you earned and money you withdrew', 'class' => 'mt-6'])

    {{-- Recent activity --}}
    <div class="card mt-6">
        <div class="card-header">
            <h3 class="card-title">Recent wallet activity</h3>
            <a href="{{ route('wallet.index') }}" class="link text-sm">View all</a>
        </div>
        @if ($recent->isEmpty())
            <x-empty icon="wallet" title="No activity yet" text="Share your referral link. Commissions appear here once your team members complete the full package." />
        @else
            <div class="table-wrap">
                <table class="table">
                    <thead><tr><th>Date</th><th>Type</th><th>Details</th><th class="text-right">Amount</th></tr></thead>
                    <tbody>
                        @foreach ($recent as $tx)
                            <tr>
                                <td class="text-ink-400">{{ $tx->created_at->format('d M Y, H:i') }}</td>
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
</x-app-layout>
