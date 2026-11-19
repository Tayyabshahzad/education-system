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
            @php $levelCount = \App\Models\Setting::levelCount(); @endphp
            <div class="card-header">
                <h3 class="card-title">Referral commission ({{ $levelCount }} levels)</h3>
            </div>
            {{-- Percent inputs with a live dollar preview based on the full fee field above. --}}
            <div class="card-body space-y-5"
                 x-data="{
                     fee: 0, pct: {},
                     read() {
                         const form = $el.closest('form');
                         this.fee = parseFloat(form.querySelector('#full_fee')?.value) || 0;
                         form.querySelectorAll('[data-level-percent]').forEach(i => this.pct[i.name] = parseFloat(i.value) || 0);
                     },
                     total() { return Object.values(this.pct).reduce((a, b) => a + b, 0); },
                     dollars(p) { return '$' + (this.fee * p / 100).toFixed(2); },
                 }"
                 x-init="read(); $el.closest('form').addEventListener('input', () => read())">
                <p class="text-sm text-ink-400">Each upline earns this <strong class="text-ink-200">percentage of what the member paid</strong> when that member completes the full fee. Level 1 is the direct sponsor. Set a level to 0 to switch it off.</p>
                <p class="rounded-lg border border-ink-700 bg-ink-800/40 px-3 py-2 text-xs text-ink-400">
                    <strong class="text-ink-200">7x7 rule:</strong> level 1 always pays. Level N (2–{{ $levelCount }}) opens once the upline has at least N active directs
                    (joining payment approved), and then pays for every full-fee member on that level. Commission from a level that isn't open yet waits,
                    and is paid automatically (at the amount it had then) as soon as the upline has enough active directs.
                </p>
                <div class="grid grid-cols-2 gap-4 sm:grid-cols-4">
                    @for ($lvl = 1; $lvl <= $levelCount; $lvl++)
                        @php $field = 'commission_level_'.$lvl; @endphp
                        <div>
                            <x-input-label :for="$field" :value="$lvl === 1 ? 'Level 1 (direct)' : 'Level '.$lvl" />
                            <div class="relative">
                                <x-text-input :id="$field" :name="$field" type="number" step="0.01" min="0" max="100" data-level-percent
                                              :value="old($field, $s[$field] ?? 0)" class="money pr-8" required />
                                <span class="pointer-events-none absolute inset-y-0 right-3.5 flex items-center text-sm text-ink-400">%</span>
                            </div>
                            <p class="field-help money" x-text="'= ' + dollars(pct['{{ $field }}'] || 0) + ' on $' + fee.toFixed(0)"></p>
                            <x-input-error :messages="$errors->get($field)" />
                        </div>
                    @endfor
                </div>
                <div class="flex flex-wrap items-center justify-between gap-2 rounded-xl border px-4 py-3 text-sm"
                     :class="total() > 100 ? 'border-bear/40 bg-bear/10' : 'border-ink-700 bg-ink-800/40'">
                    <span class="text-ink-400">All levels together, per full-fee member</span>
                    <span class="money font-semibold" :class="total() > 100 ? 'text-bear-400' : 'text-gold'"
                          x-text="total().toFixed(2).replace(/\.00$/, '') + '% = ' + dollars(total())">{{ array_sum(\App\Models\Setting::commissionLevels()) }}%</span>
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
                    <x-input-label for="commission_fee_share" value="Commission that goes to the fee for members below the full fee" />
                    <div class="relative max-w-xs">
                        <x-text-input id="commission_fee_share" name="commission_fee_share" type="number" step="0.01" min="0" max="100"
                                      :value="old('commission_fee_share', $s['commission_fee_share'] ?? 50)" class="money pr-8" required />
                        <span class="pointer-events-none absolute inset-y-0 right-3.5 flex items-center text-sm text-ink-400">%</span>
                    </div>
                    <p class="field-help">Until a member has paid the full fee, this % of every commission they earn is paid towards their remaining fee automatically (never more than they still owe). The rest stays in their wallet to withdraw. 0 = off.</p>
                    <x-input-error :messages="$errors->get('commission_fee_share')" />
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
