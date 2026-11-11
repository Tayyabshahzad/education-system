<x-app-layout title="Withdrawals" subtitle="Requested amounts are held from your wallet until processed. If a request is rejected, the amount returns to your wallet.">
    @php $balance = (float) $user->wallet_balance; @endphp

    <div class="grid gap-6 xl:grid-cols-3">
        <div class="card xl:col-span-2">
            <div class="card-header"><h3 class="card-title">My requests</h3></div>
            @if ($withdrawals->isEmpty())
                <x-empty icon="arrow-up" title="No withdrawal requests" text="Once you have earnings in your wallet you can request a payout here." />
            @else
                <div class="table-wrap">
                    <table class="table">
                        <thead><tr><th>Date</th><th>Amount</th><th>Method</th><th>Status</th><th>Note</th></tr></thead>
                        <tbody>
                            @foreach ($withdrawals as $w)
                                <tr>
                                    <td class="text-ink-400">{{ $w->created_at->format('d M Y, H:i') }}</td>
                                    <td class="money text-white">${{ number_format((float) $w->amount, 2) }}</td>
                                    <td>{{ $w->method }}</td>
                                    <td><x-status-badge :status="$w->status" /></td>
                                    <td class="max-w-xs truncate text-ink-400">{{ $w->note ?: '—' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                {{ $withdrawals->links() }}
            @endif
        </div>

        <div class="card h-max">
            <div class="card-header"><h3 class="card-title">Request a withdrawal</h3></div>
            <div class="card-body">
                <div class="mb-5 rounded-xl border border-ink-700 bg-ink-800/50 p-4">
                    <div class="text-xs text-ink-400">Available</div>
                    <div class="money mt-1 text-2xl font-semibold text-gold">${{ number_format($balance, 2) }}</div>
                </div>

                @if ($balance < $minWithdrawal)
                    <p class="text-sm text-ink-400">The minimum withdrawal is <span class="money text-white">${{ number_format($minWithdrawal, 2) }}</span>. Keep building your team to unlock payouts.</p>
                @else
                    <form method="POST" action="{{ route('withdrawals.store') }}" class="space-y-4" x-data="{ amount: {{ (float) old('amount', $balance) }} }">
                        @csrf
                        <div>
                            <x-input-label for="amount" value="Amount (USD)" />
                            <div class="relative">
                                <span class="pointer-events-none absolute inset-y-0 left-3.5 flex items-center text-sm text-ink-400">$</span>
                                <x-text-input id="amount" name="amount" type="number" step="0.01" min="{{ $minWithdrawal }}" max="{{ $balance }}" x-model="amount" class="money pl-7" required />
                            </div>
                            <x-input-error :messages="$errors->get('amount')" />
                        </div>
                        <div>
                            <x-input-label for="method" value="Payout method" />
                            <select id="method" name="method" class="field-select" required>
                                @foreach ($methods as $m)
                                    <option value="{{ $m }}" @selected(old('method') === $m)>{{ $m }}</option>
                                @endforeach
                            </select>
                            <x-input-error :messages="$errors->get('method')" />
                        </div>
                        <div>
                            <x-input-label for="account_details" value="Account details" />
                            <textarea id="account_details" name="account_details" rows="3" class="field-textarea" required placeholder="Account title, number / wallet address">{{ old('account_details') }}</textarea>
                            <x-input-error :messages="$errors->get('account_details')" />
                        </div>
                        <button class="btn btn-primary w-full">Submit request</button>
                    </form>
                @endif
            </div>
        </div>
    </div>
</x-app-layout>
