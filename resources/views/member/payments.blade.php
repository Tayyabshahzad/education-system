<x-app-layout title="Payments & Top-up" subtitle="Pay your joining fee or top up towards the full package. Upload a payment proof, or pay instantly from your wallet.">
    @php
        $paid = (float) $user->paid_amount;
        $wallet = (float) $user->wallet_balance;
    @endphp

    <div class="grid gap-6 xl:grid-cols-3">
        <div class="space-y-6 xl:col-span-2">
            {{-- Summary --}}
            <div class="grid gap-4 sm:grid-cols-3">
                <x-stat label="Paid & approved" :value="'$'.number_format($paid, 2)" tone="green" />
                <x-stat label="Full package" :value="'$'.number_format($fullFee, 2)" :hint="$paid >= $fullFee ? 'Completed' : '$'.number_format($fullFee - $paid, 2).' to go'" />
                <x-stat label="Can still pay" :value="'$'.number_format($remaining, 2)" tone="gold" :hint="'Maximum total $'.number_format($maxFee, 2)" />
            </div>

            {{-- History --}}
            <div class="card">
                <div class="card-header"><h3 class="card-title">Payment history</h3></div>
                @if ($payments->isEmpty())
                    <x-empty icon="card" title="No payments yet" text="Your submitted payments and their review status will show here." />
                @else
                    <div class="table-wrap">
                        <table class="table">
                            <thead><tr><th>Date</th><th>Amount</th><th>Method</th><th>Status</th><th>Note</th><th></th></tr></thead>
                            <tbody>
                                @foreach ($payments as $p)
                                    <tr>
                                        <td class="text-ink-400">{{ $p->created_at->format('d M Y, H:i') }}</td>
                                        <td class="money text-ink-100">${{ number_format((float) $p->amount, 2) }}</td>
                                        <td>{{ $p->methodLabel() }}</td>
                                        <td><x-status-badge :status="$p->status" /></td>
                                        <td class="max-w-xs truncate text-ink-400">{{ $p->note ?: '—' }}</td>
                                        <td class="text-right">
                                            @if ($p->proof_path)
                                                <a href="{{ route('payments.proof', $p) }}" target="_blank" class="link text-xs">View proof</a>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    {{ $payments->links() }}
                @endif
            </div>
        </div>

        {{-- New payment --}}
        <div class="card h-max">
            <div class="card-header"><h3 class="card-title">{{ $paid > 0 ? 'Top up' : 'Pay joining fee' }}</h3></div>
            <div class="card-body">
                @if ($remaining <= 0)
                    <x-empty icon="check" title="Nothing to pay" text="You have paid the maximum fee, or a payment is awaiting review." class="!py-6" />
                @else
                    <form method="POST" action="{{ route('payments.store') }}" enctype="multipart/form-data" class="space-y-5"
                          x-data="{ method: '{{ old('method', 'proof') }}', amount: {{ (float) old('amount', $suggested) }} }">
                        @csrf

                        <div>
                            <x-input-label value="Pay with" />
                            <div class="grid grid-cols-2 gap-2">
                                <label class="cursor-pointer rounded-lg border px-3 py-2.5 text-sm transition" :class="method === 'proof' ? 'border-gold bg-gold/10 text-ink-100' : 'border-ink-700 bg-ink-800/60 text-ink-400'">
                                    <input type="radio" name="method" value="proof" x-model="method" class="sr-only">
                                    <span class="flex items-center gap-2 font-medium"><x-icon name="upload" class="h-4 w-4" /> Upload proof</span>
                                </label>
                                <label class="cursor-pointer rounded-lg border px-3 py-2.5 text-sm transition" :class="method === 'wallet' ? 'border-gold bg-gold/10 text-ink-100' : 'border-ink-700 bg-ink-800/60 text-ink-400'">
                                    <input type="radio" name="method" value="wallet" x-model="method" class="sr-only" @disabled($wallet < $minimum)>
                                    <span class="flex items-center gap-2 font-medium"><x-icon name="wallet" class="h-4 w-4" /> Wallet</span>
                                    <span class="money mt-0.5 block text-xs text-ink-400">${{ number_format($wallet, 2) }}</span>
                                </label>
                            </div>
                        </div>

                        <div>
                            <x-input-label for="amount" value="Amount (USD)" />
                            <div class="relative">
                                <span class="pointer-events-none absolute inset-y-0 left-3.5 flex items-center text-sm text-ink-400">$</span>
                                <x-text-input id="amount" name="amount" type="number" step="0.01" min="{{ $minimum }}" max="{{ $remaining }}" x-model="amount" class="money pl-7" required />
                            </div>
                            <p class="field-help">Between ${{ number_format($minimum, 2) }} and ${{ number_format($remaining, 2) }}.</p>
                            <p x-show="method === 'wallet' && Number(amount) > {{ $wallet }}" x-cloak class="mt-1.5 text-xs text-bear-400">Amount is higher than your wallet balance.</p>
                            <x-input-error :messages="$errors->get('amount')" />
                        </div>

                        {{-- x-if (not x-show) so the required screenshot input is removed when paying from wallet --}}
                        <template x-if="method === 'proof'">
                        <div class="space-y-5">
                            @if ($instructions)
                                <div class="rounded-lg border border-ink-700 bg-ink-800/50 p-4">
                                    <div class="mb-1.5 text-xs font-semibold uppercase tracking-wide text-ink-400">Send payment to</div>
                                    <p class="whitespace-pre-line text-sm text-ink-200">{{ $instructions }}</p>
                                </div>
                            @endif
                            <x-proof-upload />
                            <div>
                                <x-input-label for="reference" value="Transaction ID / reference (optional)" />
                                <x-text-input id="reference" name="reference" :value="old('reference')" />
                            </div>
                        </div>
                        </template>

                        <button class="btn btn-primary w-full" :disabled="method === 'wallet' && Number(amount) > {{ $wallet }}">
                            <span x-text="method === 'wallet' ? 'Pay from wallet' : 'Submit for review'">Submit for review</span>
                        </button>
                    </form>
                @endif
            </div>
        </div>
    </div>
</x-app-layout>
