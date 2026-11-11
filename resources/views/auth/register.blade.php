<x-guest-layout title="Create account" width="max-w-xl">
    <div class="mb-7">
        <h2 class="font-display text-3xl font-semibold text-white">Start your trading journey</h2>
        <p class="mt-2 text-sm text-ink-400">Choose your package and create your account. You will upload your payment proof after registration.</p>
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
        <fieldset>
            <legend class="mb-3 flex items-center gap-2 text-sm font-medium text-ink-200">
                <span class="flex h-5 w-5 items-center justify-center rounded-full bg-gold text-[11px] font-bold text-ink-950">1</span>
                Choose your package
            </legend>
            <div class="grid gap-3 sm:grid-cols-2">
                @foreach ($packages as $key => $pkg)
                    <label class="group relative cursor-pointer">
                        <input type="radio" name="package" value="{{ $key }}" class="peer sr-only" @checked(old('package', 'full') === $key) required>
                        <div class="h-full rounded-xl border border-ink-700 bg-ink-800/60 p-4 transition group-hover:border-ink-600 peer-checked:border-gold peer-checked:bg-gold/5 peer-checked:glow-gold peer-focus-visible:ring-2 peer-focus-visible:ring-gold/50">
                            <div class="flex items-start justify-between">
                                <span class="text-sm font-medium text-ink-200">{{ $pkg['label'] }}</span>
                                @if ($key === 'full')
                                    <span class="rounded-full bg-gold/15 px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-gold">Recommended</span>
                                @endif
                            </div>
                            <div class="mt-2 font-display text-3xl font-semibold text-white">${{ number_format($pkg['amount']) }}</div>
                            <p class="mt-1 text-xs text-ink-400">
                                {{ $key === 'full' ? 'Complete training access. Activates referral earnings for your sponsor.' : 'Entry access. Upgrade to the full package anytime from your dashboard.' }}
                            </p>
                        </div>
                    </label>
                @endforeach
            </div>
            <x-input-error :messages="$errors->get('package')" />
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
