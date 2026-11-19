{{--
    Always-visible dashboard banner when 7x7 commission is waiting for this user.
    Expects: $nextUnlock (User::nextCommissionUnlock(), may be null), $refUser.
--}}
@if ($nextUnlock)
    @php
        $n = $nextUnlock['directsNeeded'];
        $money = fn ($v) => '$'.number_format($v, 2);
    @endphp
    <div class="mb-6 overflow-hidden rounded-2xl border border-gold/40 bg-gradient-to-r from-gold/15 via-gold/5 to-transparent p-5" role="status">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div class="flex items-start gap-4">
                <span class="flex h-12 w-12 shrink-0 items-center justify-center rounded-full bg-gold/20 text-2xl" aria-hidden="true">⏳</span>
                <div>
                    <p class="text-sm text-ink-200">
                        You have <strong class="money text-lg text-gold">{{ $money($nextUnlock['total']) }}</strong> commission waiting
                        from {{ $nextUnlock['waitingCount'] }} {{ Str::plural('member', $nextUnlock['waitingCount']) }} in your team.
                    </p>
                    <p class="mt-1 font-display text-lg font-semibold text-ink-100">
                        Bring {{ $n }} more active {{ Str::plural('direct', $n) }} and
                        <span class="money text-bull-400">{{ $money($nextUnlock['releaseAmount']) }}</span> goes into your wallet right away!
                    </p>
                    <p class="mt-1 text-xs text-ink-400">
                        Under the 7x7 rule, level N pays once you have N active directs (joining payment approved). The rest is released as your team grows — nothing is lost.
                    </p>
                </div>
            </div>
            <div class="flex w-full flex-col gap-2 sm:w-auto sm:flex-row sm:flex-wrap">
                <x-copy-field :value="$refUser->referralLink()" label="Copy my link" class="w-full sm:w-64" />
                <a href="{{ route('team.create') }}" class="btn btn-primary justify-center"><x-icon name="user-plus" class="h-4 w-4" /> Register a member</a>
            </div>
        </div>
    </div>
@endif
