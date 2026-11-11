<x-app-layout title="Settings" subtitle="Changes apply to payments approved from now on. Commission already paid is never recalculated.">
    @php $s = $settings; @endphp
    <form method="POST" action="{{ route('admin.settings.update') }}" class="max-w-4xl space-y-6">
        @csrf @method('PUT')

        <div class="card">
            <div class="card-header"><h3 class="card-title">Fees</h3></div>
            <div class="card-body grid gap-5 sm:grid-cols-3">
                @foreach ([
                    'min_join_fee' => ['Minimum joining fee', 'Lowest first payment a new member can make.'],
                    'full_fee' => ['Full package fee', 'Paid total that unlocks classes and releases upline commission.'],
                    'max_fee' => ['Maximum fee', 'Highest total a member can pay (joining + top-ups).'],
                ] as $key => [$label, $help])
                    <div>
                        <x-input-label :for="$key" :value="$label" />
                        <div class="relative">
                            <span class="pointer-events-none absolute inset-y-0 left-3.5 flex items-center text-sm text-ink-400">$</span>
                            <x-text-input :id="$key" :name="$key" type="number" step="0.01" min="1" :value="old($key, $s[$key])" class="money pl-7" required />
                        </div>
                        <p class="field-help">{{ $help }}</p>
                        <x-input-error :messages="$errors->get($key)" />
                    </div>
                @endforeach
            </div>
        </div>

        <div class="card">
            <div class="card-header"><h3 class="card-title">Referral commission</h3></div>
            <div class="card-body space-y-5">
                <div class="grid gap-5 sm:grid-cols-3">
                    @foreach ([1 => 'Level 1 (direct sponsor)', 2 => 'Level 2', 3 => 'Level 3'] as $lvl => $label)
                        <div>
                            <x-input-label :for="'commission_level_'.$lvl" :value="$label" />
                            <div class="relative">
                                <span class="pointer-events-none absolute inset-y-0 left-3.5 flex items-center text-sm text-ink-400">$</span>
                                <x-text-input :id="'commission_level_'.$lvl" :name="'commission_level_'.$lvl" type="number" step="0.01" min="0" :value="old('commission_level_'.$lvl, $s['commission_level_'.$lvl])" class="money pl-7" required />
                            </div>
                            <x-input-error :messages="$errors->get('commission_level_'.$lvl)" />
                        </div>
                    @endforeach
                </div>
                <label class="flex items-start gap-3 text-sm">
                    <input type="checkbox" name="upline_must_be_full" value="1" @checked(old('upline_must_be_full', filter_var($s['upline_must_be_full'], FILTER_VALIDATE_BOOLEAN)))
                           class="mt-0.5 rounded border-ink-600 bg-ink-800 text-gold focus:ring-gold/40 focus:ring-offset-ink-950">
                    <span>
                        <span class="font-medium text-ink-100">Upline must be on the full package to earn</span>
                        <span class="block text-xs text-ink-400">When off, any active upline earns. When on, uplines who paid less than the full fee are skipped for that level.</span>
                    </span>
                </label>
            </div>
        </div>

        <div class="card">
            <div class="card-header"><h3 class="card-title">Payments & withdrawals</h3></div>
            <div class="card-body grid gap-5 sm:grid-cols-2">
                <div>
                    <x-input-label for="min_withdrawal" value="Minimum withdrawal" />
                    <div class="relative">
                        <span class="pointer-events-none absolute inset-y-0 left-3.5 flex items-center text-sm text-ink-400">$</span>
                        <x-text-input id="min_withdrawal" name="min_withdrawal" type="number" step="0.01" min="1" :value="old('min_withdrawal', $s['min_withdrawal'])" class="money pl-7" required />
                    </div>
                </div>
                <div>
                    <x-input-label for="withdrawal_methods" value="Withdrawal methods (comma separated)" />
                    <x-text-input id="withdrawal_methods" name="withdrawal_methods" :value="old('withdrawal_methods', $s['withdrawal_methods'])" required />
                </div>
                <div class="sm:col-span-2">
                    <x-input-label for="payment_instructions" value="Payment instructions shown to members" />
                    <textarea id="payment_instructions" name="payment_instructions" rows="4" class="field-textarea font-mono">{{ old('payment_instructions', $s['payment_instructions']) }}</textarea>
                    <p class="field-help">Bank / Easypaisa / USDT account details members should send money to.</p>
                </div>
            </div>
        </div>

        <button class="btn btn-primary">Save settings</button>
    </form>
</x-app-layout>
