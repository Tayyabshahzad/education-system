<x-guest-layout title="Sign in">
    <div class="mb-8">
        <h2 class="font-display text-3xl font-semibold text-white">Welcome back</h2>
        <p class="mt-2 text-sm text-ink-400">Sign in to continue your forex training.</p>
    </div>

    <x-auth-session-status class="mb-5" :status="session('status')" />

    <form method="POST" action="{{ route('login') }}" class="space-y-5">
        @csrf

        <div>
            <x-input-label for="email" value="Email address" />
            <x-text-input id="email" type="email" name="email" :value="old('email')" required autofocus autocomplete="username" placeholder="you@example.com" />
            <x-input-error :messages="$errors->get('email')" />
        </div>

        <div>
            <div class="flex items-center justify-between">
                <x-input-label for="password" value="Password" />
                @if (Route::has('password.request'))
                    <a href="{{ route('password.request') }}" class="mb-1.5 text-xs font-medium text-gold hover:text-gold-400">Forgot password?</a>
                @endif
            </div>
            <x-text-input id="password" type="password" name="password" required autocomplete="current-password" placeholder="••••••••" />
            <x-input-error :messages="$errors->get('password')" />
        </div>

        <label for="remember_me" class="flex items-center gap-2.5 text-sm text-ink-400">
            <input id="remember_me" type="checkbox" name="remember" class="rounded border-ink-600 bg-ink-800 text-gold focus:ring-gold/40 focus:ring-offset-ink-950">
            Keep me signed in
        </label>

        <x-primary-button class="w-full py-3">
            Sign in
            <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M3 10a.75.75 0 0 1 .75-.75h10.64l-3.22-3.22a.75.75 0 1 1 1.06-1.06l4.5 4.5a.75.75 0 0 1 0 1.06l-4.5 4.5a.75.75 0 1 1-1.06-1.06l3.22-3.22H3.75A.75.75 0 0 1 3 10Z" clip-rule="evenodd"/></svg>
        </x-primary-button>
    </form>

    <p class="mt-8 text-center text-sm text-ink-400">
        New to Global Visioner?
        <a href="{{ route('register') }}" class="font-semibold text-gold hover:text-gold-400">Create an account</a>
    </p>
</x-guest-layout>
