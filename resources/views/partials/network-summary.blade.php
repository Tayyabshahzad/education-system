{{--
    Referral link + earnings block for teacher, staff and super-admin dashboards.
    Expects the same variables as partials.referral-card plus $totalEarned and $personalChart.
--}}
<div class="grid gap-6 xl:grid-cols-3">
    @include('partials.referral-card', ['class' => 'xl:col-span-2'])

    <div class="card">
        <div class="card-header"><h3 class="card-title">Your earnings</h3></div>
        <div class="card-body space-y-4">
            <div>
                <div class="text-xs text-ink-400">Wallet balance</div>
                <div class="money mt-1 font-display text-2xl font-semibold text-gold">${{ number_format((float) $refUser->wallet_balance, 2) }}</div>
            </div>
            <div>
                <div class="text-xs text-ink-400">Total commission earned</div>
                <div class="money mt-1 text-lg font-semibold text-bull-400">${{ number_format($totalEarned, 2) }}</div>
            </div>
            <div class="grid grid-cols-2 gap-2 pt-1">
                <a href="{{ route('team.index') }}" class="btn btn-secondary btn-sm"><x-icon name="users" class="h-4 w-4" /> My team</a>
                <a href="{{ route('withdrawals.index') }}" class="btn btn-primary btn-sm"><x-icon name="arrow-up" class="h-4 w-4" /> Withdraw</a>
            </div>
        </div>
    </div>
</div>

@include('partials.metrics-chart', ['chart' => $personalChart, 'title' => 'My performance', 'subtitle' => 'People who joined your 3-level team, commission you earned and money you withdrew', 'class' => 'mt-6'])
