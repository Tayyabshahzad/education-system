<x-app-layout title="Wallet">
    <x-slot:actions>
        <a href="{{ route('withdrawals.index') }}" class="btn btn-primary btn-sm"><x-icon name="arrow-up" class="h-4 w-4" /> Withdraw</a>
    </x-slot:actions>

    <div class="mb-6 grid gap-4 sm:grid-cols-2">
        <x-stat label="Available balance" :value="'$'.number_format((float) $user->wallet_balance, 2)" tone="gold" icon="wallet" hint="Use it to withdraw, top up, or pay for members you register" />
        <x-stat label="Total commission earned" :value="'$'.number_format($totalEarned, 2)" tone="green" icon="chart" />
    </div>

    <div class="card">
        <div class="card-header">
            <h3 class="card-title">Transactions</h3>
            <div class="flex flex-wrap gap-1.5">
                @foreach (['' => 'All', 'commission' => 'Commission', 'withdrawal' => 'Withdrawals', 'withdrawal_refund' => 'Refunds', 'payment' => 'Fee payments'] as $key => $label)
                    <a href="{{ route('wallet.index', array_filter(['type' => $key])) }}"
                       @class(['rounded-md px-2.5 py-1 text-xs font-medium', 'bg-gold/15 text-gold' => $type === $key || (! $type && $key === ''), 'text-ink-400 hover:text-ink-100' => ! ($type === $key || (! $type && $key === ''))])>{{ $label }}</a>
                @endforeach
            </div>
        </div>
        @if ($transactions->isEmpty())
            <x-empty icon="wallet" title="No transactions" />
        @else
            <div class="table-wrap">
                <table class="table">
                    <thead><tr><th>Date</th><th>Type</th><th>Details</th><th class="text-right">Amount</th><th class="text-right">Balance</th></tr></thead>
                    <tbody>
                        @foreach ($transactions as $tx)
                            <tr>
                                <td class="text-ink-400">{{ $tx->created_at->format('d M Y, H:i') }}</td>
                                <td>
                                    {{ $tx->typeLabel() }}
                                    @if ($tx->level)<span class="badge badge-gray ml-1">L{{ $tx->level }}</span>@endif
                                </td>
                                <td class="text-ink-400">{{ $tx->description }}</td>
                                <td class="money text-right {{ $tx->amount >= 0 ? 'text-bull-400' : 'text-bear-400' }}">{{ $tx->amount >= 0 ? '+' : '−' }}${{ number_format(abs((float) $tx->amount), 2) }}</td>
                                <td class="money text-right text-ink-200">${{ number_format((float) $tx->balance_after, 2) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            {{ $transactions->links() }}
        @endif
    </div>
</x-app-layout>
