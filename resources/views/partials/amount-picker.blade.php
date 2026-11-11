{{--
    Amount input with quick picks. Expects an Alpine scope with `amount`.
    Vars: $name, $minFee, $fullFee, $maxFee
--}}
@php
    $picks = array_values(array_unique([$minFee, $fullFee, $maxFee]));
@endphp
<div @class(['grid gap-2', 'grid-cols-1' => count($picks) === 1, 'grid-cols-2' => count($picks) === 2, 'grid-cols-3' => count($picks) >= 3])>
    @foreach ($picks as $pick)
        <button type="button" @click="amount = {{ $pick }}"
                class="rounded-lg border px-3 py-2.5 text-left transition"
                :class="Number(amount) === {{ $pick }} ? 'border-gold bg-gold/10' : 'border-ink-700 bg-ink-800/60 hover:border-ink-600'">
            <span class="money block text-base font-semibold text-white">${{ number_format($pick, 0) }}</span>
            <span class="block text-[11px] text-ink-400">{{ $pick == $fullFee ? 'Full package' : ($pick == $minFee ? 'Joining fee' : 'Maximum') }}</span>
        </button>
    @endforeach
</div>
<div>
    <x-input-label :for="$name" value="Amount (USD)" />
    <div class="relative">
        <span class="pointer-events-none absolute inset-y-0 left-3.5 flex items-center text-sm text-ink-400">$</span>
        <x-text-input :id="$name" :name="$name" type="number" step="0.01" min="{{ $minFee }}" max="{{ $maxFee }}" x-model="amount" class="money pl-7" required />
    </div>
    <p class="field-help">Between ${{ number_format($minFee, 2) }} and ${{ number_format($maxFee, 2) }}.</p>
    <p x-show="Number(amount) < {{ $fullFee }}" x-cloak class="mt-2 rounded-lg bg-gold/10 px-3 py-2 text-xs text-gold">
        Below ${{ number_format($fullFee, 0) }}: no class batch and no sponsor commission until topped up to ${{ number_format($fullFee, 0) }}.
    </p>
    <x-input-error :messages="$errors->get($name)" />
</div>
