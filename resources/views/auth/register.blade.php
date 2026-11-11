<x-guest-layout title="Create account" width="max-w-xl">
    <div class="mb-7">
        <h2 class="font-display text-3xl font-semibold text-white">Start your trading journey</h2>
        <p class="mt-2 text-sm text-ink-400">Choose your amount and create your account. You will submit your payment proof after registration.</p>
    </div>

    <form method="POST" action="{{ route('register') }}" class="space-y-6">
        @csrf

        {{-- Referral --}}
        @if ($sponsor)
            <div class="flex items-center gap-3 rounded-xl border border-bull/30 bg-bull/10 px-4 py-3">
                <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-bull/20 font-semibold text-bull-400">
                    {{ strtoupper(substr($sponsor->name, 0, 1)) }}
                </div>
                <div class="text-sm">
                    <div class="text-ink-400">Referred by</div>
                    <div class="font-medium text-white">{{ $sponsor->name }} <span class="font-mono text-xs text-ink-400">({{ $sponsor->referral_code }})</span></div>
                </div>
            </div>
            <input type="hidden" name="sponsor_code" value="{{ $sponsor->referral_code }}">
        @else
            <div>
                <x-input-label for="sponsor_code" value="Referral code (optional)" />
                <x-text-input id="sponsor_code" name="sponsor_code" :value="$refCode" class="font-mono uppercase placeholder:normal-case" placeholder="e.g. GVEAB12CD" />
                <x-input-error :messages="$errors->get('sponsor_code')" />
            </div>
        @endif

        {{-- Step 1: package --}}
        <fieldset x-data="{ amount: {{ (float) old('joining_amount', $fullFee) }} }">
            <legend class="mb-3 flex items-center gap-2 text-sm font-medium text-ink-200">
                <span class="flex h-5 w-5 items-center justify-center rounded-full bg-gold text-[11px] font-bold text-ink-950">1</span>
                Choose your amount
            </legend>
            <div class="grid gap-3 sm:grid-cols-2">
                @foreach ([[$minFee, 'Joining Fee', 'Entry access. Top up to the full package anytime from your dashboard.'], [$fullFee, 'Full Training Package', 'Live class batches and full academy access.']] as [$value, $label, $text])
                    <button type="button" @click="amount = {{ $value }}"
                            class="h-full rounded-xl border p-4 text-left transition"
                            :class="Number(amount) === {{ $value }} ? 'border-gold bg-gold/5 glow-gold' : 'border-ink-700 bg-ink-800/60 hover:border-ink-600'">
                        <div class="flex items-start justify-between">
                            <span class="text-sm font-medium text-ink-200">{{ $label }}</span>
                            @if ($value == $fullFee)
                                <span class="rounded-full bg-gold/15 px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-gold">Recommended</span>
                            @endif
                        </div>
                        <div class="mt-2 font-display text-3xl font-semibold text-white">${{ number_format($value) }}</div>
                        <p class="mt-1 text-xs text-ink-400">{{ $text }}</p>
                    </button>
                @endforeach
            </div>
            @if ($maxFee != $minFee)
                <div class="mt-3 flex items-center gap-3">
                    <label for="joining_amount" class="shrink-0 text-xs text-ink-400">Or enter an amount</label>
                    <div class="relative flex-1">
                        <span class="pointer-events-none absolute inset-y-0 left-3.5 flex items-center text-sm text-ink-400">$</span>
                        <x-text-input id="joining_amount" name="joining_amount" type="number" step="0.01" min="{{ $minFee }}" max="{{ $maxFee }}" x-model="amount" class="money pl-7" required />
                    </div>
                </div>
            @else
                <input type="hidden" name="joining_amount" :value="amount">
            @endif
            <p class="field-help">Between ${{ number_format($minFee, 2) }} and ${{ number_format($maxFee, 2) }}.</p>
            <x-input-error :messages="$errors->get('joining_amount')" />
        </fieldset>

        {{-- Step 2: details --}}
        <fieldset class="space-y-4">
            <legend class="mb-3 flex items-center gap-2 text-sm font-medium text-ink-200">
                <span class="flex h-5 w-5 items-center justify-center rounded-full bg-gold text-[11px] font-bold text-ink-950">2</span>
                Your details
            </legend>

            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <x-input-label for="name" value="Full name" />
                    <x-text-input id="name" name="name" :value="old('name')" required autofocus autocomplete="name" />
                    <x-input-error :messages="$errors->get('name')" />
                </div>
                <div>
                    <x-input-label for="username" value="Username" />
                    <x-text-input id="username" name="username" :value="old('username')" required autocomplete="username" />
                    <x-input-error :messages="$errors->get('username')" />
                </div>
            </div>

            <div>
                <x-input-label for="email" value="Email address" />
                <x-text-input id="email" type="email" name="email" :value="old('email')" required autocomplete="email" />
                <x-input-error :messages="$errors->get('email')" />
            </div>

            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <x-input-label for="phone" value="Phone / WhatsApp" />
                    <x-text-input id="phone" type="tel" name="phone" :value="old('phone')" required autocomplete="tel" placeholder="+92 300 0000000" />
                    <x-input-error :messages="$errors->get('phone')" />
                </div>
                <div>
                    <x-input-label for="country" value="Country" />
                    <x-text-input id="country" name="country" :value="old('country')" required autocomplete="country-name" />
                    <x-input-error :messages="$errors->get('country')" />
                </div>
            </div>

            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <x-input-label for="password" value="Password" />
                    <x-text-input id="password" type="password" name="password" required autocomplete="new-password" />
                    <x-input-error :messages="$errors->get('password')" />
                </div>
                <div>
                    <x-input-label for="password_confirmation" value="Confirm password" />
                    <x-text-input id="password_confirmation" type="password" name="password_confirmation" required autocomplete="new-password" />
                </div>
            </div>
        </fieldset>

        <div>
            <label class="flex items-start gap-2.5 text-sm text-ink-400">
                <input type="checkbox" name="terms" value="1" class="mt-0.5 rounded border-ink-600 bg-ink-800 text-gold focus:ring-gold/40 focus:ring-offset-ink-950" @checked(old('terms')) required>
                <span>I understand that forex trading carries risk and this program is for educational purposes only.</span>
            </label>
            <x-input-error :messages="$errors->get('terms')" />
        </div>

        <x-primary-button class="w-full py-3">Create account</x-primary-button>
    </form>

    <p class="mt-8 text-center text-sm text-ink-400">
        Already a member?
        <a href="{{ route('login') }}" class="font-semibold text-gold hover:text-gold-400">Sign in</a>
    </p>
</x-guest-layout>
