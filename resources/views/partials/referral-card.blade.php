{{--
    Referral link card, shown on every role's dashboard.
    Expects: $refUser, $levels (downlineByLevel), $earningsByLevel, $commissionLevels, $fullFee, optional $class.

    The link and code are always visible. The level breakdown can be collapsed, but it is
    open on every page load so nothing feels hidden.
--}}
<div x-data="{ open: true }" @class(['card', $class ?? ''])>
    <div class="card-header">
        <h3 class="card-title">Your referral link</h3>
        <div class="flex items-center gap-3">
            <span class="font-mono text-xs text-ink-400">Code: <span class="text-gold">{{ $refUser->referral_code }}</span></span>
            <button type="button" @click="open = !open" :aria-expanded="open.toString()" aria-controls="referral-details"
                    class="flex items-center gap-1 rounded-md border border-ink-700 px-2 py-1 text-xs font-medium text-ink-200 hover:border-gold/40 hover:text-gold">
                <span x-text="open ? 'Hide details' : 'Show details'">Hide details</span>
                <svg class="h-3.5 w-3.5 transition-transform" :class="open ? 'rotate-180' : ''" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                    <path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 0 1 1.06.02L10 11.17l3.71-3.94a.75.75 0 1 1 1.08 1.04l-4.25 4.5a.75.75 0 0 1-1.08 0l-4.25-4.5a.75.75 0 0 1 .02-1.06Z" clip-rule="evenodd" />
                </svg>
            </button>
        </div>
    </div>
    <div class="card-body space-y-5">
        <x-copy-field :value="$refUser->referralLink()" label="Copy link" />
        <div id="referral-details" x-show="open" x-transition.opacity class="space-y-5">
            <div class="grid grid-cols-2 gap-2.5 sm:grid-cols-4">
                @foreach ($commissionLevels as $level => $amount)
                    <div class="rounded-xl border border-ink-700 bg-ink-800/50 p-3">
                        <div class="flex items-center justify-between gap-2 text-xs text-ink-400">
                            <span>Level {{ $level }}</span>
                            <span class="money text-gold">${{ number_format($amount, $amount == floor($amount) ? 0 : 2) }}</span>
                        </div>
                        <div class="mt-1.5 font-display text-lg font-semibold text-ink-100">{{ $levels[$level]->count() }} <span class="text-xs font-normal text-ink-400">{{ Str::plural('member', $levels[$level]->count()) }}</span></div>
                        <div class="mt-0.5 text-xs text-bull-400">Earned <span class="money">${{ number_format((float) ($earningsByLevel[$level] ?? 0), 2) }}</span></div>
                    </div>
                @endforeach
                {{-- Totals across every level --}}
                @php $teamTotal = collect($levels)->sum(fn ($l) => $l->count()); @endphp
                <div class="rounded-xl border border-gold/30 bg-gold/5 p-3">
                    <div class="text-xs text-ink-400">All {{ count($commissionLevels) }} levels</div>
                    <div class="mt-1.5 font-display text-lg font-semibold text-ink-100">{{ $teamTotal }} <span class="text-xs font-normal text-ink-400">{{ Str::plural('member', $teamTotal) }}</span></div>
                    <div class="mt-0.5 text-xs text-bull-400">Earned <span class="money">${{ number_format((float) collect($earningsByLevel)->sum(), 2) }}</span></div>
                </div>
            </div>
            <p class="text-xs text-ink-400">You earn the amount shown for each level when a member anywhere in your {{ count($commissionLevels) }}-level team completes the full <span class="money">${{ number_format($fullFee, 0) }}</span> package.</p>
        </div>
    </div>
</div>
