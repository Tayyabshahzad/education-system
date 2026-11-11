<x-app-layout title="Register a Member" subtitle="Create an account for someone directly under you. They will appear in your level 1 team.">
    <form method="POST" action="{{ route('team.store') }}" class="grid gap-6 xl:grid-cols-3"
          x-data="{ amount: {{ (float) old('joining_amount', $fullFee) }}, payNow: {{ old('pay_from_wallet') ? 'true' : 'false' }}, wallet: {{ $wallet }} }">
        @csrf

        <div class="card xl:col-span-2">
            <div class="card-header"><h3 class="card-title">Member details</h3></div>
            <div class="card-body">
                @include('partials.member-fields')
            </div>
        </div>

        <div class="space-y-6">
            <div class="card">
                <div class="card-header"><h3 class="card-title">Joining amount</h3></div>
                <div class="card-body space-y-4">
                    @include('partials.amount-picker', ['name' => 'joining_amount'])
                </div>
            </div>

            <div class="card">
                <div class="card-header"><h3 class="card-title">Payment</h3></div>
                <div class="card-body space-y-4 text-sm">
                    <label class="flex items-start gap-3">
                        <input type="checkbox" name="pay_from_wallet" value="1" x-model="payNow" class="mt-0.5 rounded border-ink-600 bg-ink-800 text-gold focus:ring-gold/40 focus:ring-offset-ink-950">
                        <span>
                            <span class="font-medium text-white">Pay now from my wallet</span>
                            <span class="block text-xs text-ink-400">Balance: <span class="money text-gold">${{ number_format($wallet, 2) }}</span>. The account is activated immediately.</span>
                        </span>
                    </label>
                    <p x-show="payNow && amount > wallet" x-cloak class="rounded-lg bg-bear/10 px-3 py-2 text-xs text-bear-400">Your wallet balance is lower than the joining amount.</p>
                    <p x-show="!payNow" class="text-xs text-ink-400">If unchecked, the member logs in and submits their own payment proof.</p>
                    <x-input-error :messages="$errors->get('pay_from_wallet')" />
                    <button class="btn btn-primary w-full" :disabled="payNow && amount > wallet">Register member</button>
                </div>
            </div>
        </div>
    </form>
</x-app-layout>
