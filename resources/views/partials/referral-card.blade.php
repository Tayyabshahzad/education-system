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
            {{-- 7x7 rule: how many levels this user has unlocked --}}
            @php $openLevels = collect($levelStatus)->filter(fn ($s) => $s['open'])->keys()->max() ?? 1; @endphp
            <div class="flex flex-wrap items-center justify-between gap-2 rounded-xl border border-ink-700 bg-ink-800/40 px-4 py-3 text-sm">
                <span class="text-ink-200">
                    You have <strong class="text-gold">{{ $qualifiedDirects }} active {{ Str::plural('direct', $qualifiedDirects) }}</strong>
                    — levels 1{{ $openLevels > 1 ? '–'.$openLevels : '' }} open.
                </span>
                @if ($openLevels < count($commissionLevels))
                    <span class="text-xs text-ink-400">Bring {{ ($openLevels + 1) - $qualifiedDirects }} more active {{ Str::plural('direct', ($openLevels + 1) - $qualifiedDirects) }} to open level {{ $openLevels + 1 }}.</span>
                @endif
            </div>

            <div class="grid grid-cols-2 gap-2.5 sm:grid-cols-4">
                @foreach ($commissionLevels as $level => $percent)
                    @php
                        $perMember = round($fullFee * $percent / 100, 2);
                        $st = $levelStatus[$level];
                    @endphp
                    <div @class(['rounded-xl border p-3', 'border-ink-700 bg-ink-800/50' => $st['open'], 'border-dashed border-ink-700 bg-ink-900/40' => ! $st['open']])>
                        <div class="flex items-center justify-between gap-2 text-xs text-ink-400">
                            <span>Level {{ $level }}</span>
                            <span class="money text-gold" title="{{ $percent + 0 }}% of ${{ number_format($fullFee, 0) }}">{{ $percent + 0 }}% · ${{ number_format($perMember, $perMember == floor($perMember) ? 0 : 2) }}</span>
                        </div>
                        <div class="mt-1.5 font-display text-lg font-semibold text-ink-100">{{ $levels[$level]->count() }} <span class="text-xs font-normal text-ink-400">{{ Str::plural('member', $levels[$level]->count()) }}</span></div>
                        <div class="mt-0.5 text-xs text-bull-400">Earned <span class="money">${{ number_format((float) ($earningsByLevel[$level] ?? 0), 2) }}</span></div>
                        <div class="mt-1.5 text-[11px]">
                            @if (! $st['open'])
                                <span class="text-ink-400">🔒 Needs {{ $st['needs'] }} directs</span>
                            @elseif ($st['slots'] === null)
                                <span class="text-ink-400">Open · no limit</span>
                            @else
                                <span @class(['text-ink-400' => $st['used'] < $st['slots'], 'text-bear-400' => $st['used'] >= $st['slots']])>
                                    Open · {{ min($st['used'], $st['slots']) }}/{{ $st['slots'] }} paid{{ $st['used'] >= $st['slots'] ? ' (full)' : '' }}
                                </span>
                            @endif
                            @if ($st['waiting'] > 0)
                                <span class="mt-0.5 block text-gold">⏳ {{ $st['waiting'] }} waiting · ${{ number_format($st['waitingAmount'], 2) }}</span>
                            @endif
                        </div>
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
            <p class="text-xs text-ink-400">You earn each level's percentage of what a member paid when they complete the full <span class="money">${{ number_format($fullFee, 0) }}</span> package. Level 1 always pays. Level N opens when you have N active directs, and then pays for every full-fee member on that level. Anything you can't receive yet waits and is paid automatically once you have the directs. Dollar amounts shown are for a <span class="money">${{ number_format($fullFee, 0) }}</span> member.</p>
        </div>
    </div>
</div>
