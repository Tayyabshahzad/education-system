<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#0B1530">

    <title>{{ $title ? $title.' · ' : '' }}{{ config('app.name') }}</title>

    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700|space-grotesk:500,600,700|jetbrains-mono:500&display=swap" rel="stylesheet" />

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="h-full font-sans antialiased">
@php
    $authUser = auth()->user();
    $isMember = $authUser->isMember();
    $isTeacher = $authUser->hasRole(\App\Models\User::ROLE_TEACHER);
    $isStaffSide = $authUser->isStaffSide();

    // [label, route, icon, active-pattern, visible]
    $sections = [];

    if ($isMember) {
        $sections['Trading Desk'] = [
            ['Dashboard', 'dashboard', 'home', 'dashboard', true],
            ['My Classes', 'classes.index', 'academic', 'classes.*', true],
        ];
        $sections['My Network'] = [
            ['Team', 'team.index', 'users', 'team.index', true],
            ['Genealogy Tree', 'team.tree', 'tree', 'team.tree', true],
            ['Register Member', 'team.create', 'user-plus', 'team.create', true],
        ];
        $sections['Funds'] = [
            ['Payments & Top-up', 'payments.index', 'card', 'payments.*', true],
            ['Wallet', 'wallet.index', 'wallet', 'wallet.*', true],
            ['Withdrawals', 'withdrawals.index', 'arrow-up', 'withdrawals.*', true],
        ];
    }

    if ($isTeacher) {
        $sections['Teaching'] = [
            ['My Batches', 'dashboard', 'academic', 'dashboard', true],
        ];
    }

    if ($isStaffSide) {
        $sections['Overview'] = [
            ['Dashboard', 'dashboard', 'home', 'dashboard', true],
        ];
        $sections['Members'] = [
            ['All Members', 'admin.members.index', 'users', 'admin.members.*', $authUser->can('members.view')],
            ['Add User', 'admin.users.create', 'user-plus', 'admin.users.*', $authUser->canAny(['members.create', 'staff.manage', 'teachers.manage'])],
            ['Genealogy', 'admin.genealogy', 'tree', 'admin.genealogy', $authUser->can('members.view')],
        ];
        $sections['Finance'] = [
            ['Payments', 'admin.payments.index', 'card', 'admin.payments.*', $authUser->can('payments.view')],
            ['Withdrawals', 'admin.withdrawals.index', 'arrow-up', 'admin.withdrawals.*', $authUser->can('withdrawals.view')],
        ];
        $sections['Academy'] = [
            ['Batches', 'admin.batches.index', 'calendar', 'admin.batches.*', $authUser->can('batches.manage')],
            ['Teachers', 'admin.teachers.index', 'teacher', 'admin.teachers.*', $authUser->can('teachers.manage')],
        ];
        $sections['System'] = [
            ['Staff & Access', 'admin.staff.index', 'shield', 'admin.staff.*', $authUser->can('staff.manage')],
            ['Settings', 'admin.settings.edit', 'cog', 'admin.settings.*', $authUser->can('settings.manage')],
        ];
    }

    $roleLabel = [
        'super-admin' => 'Super Admin', 'staff' => 'Staff', 'teacher' => 'Teacher', 'member' => 'Member',
    ][$authUser->primaryRole()] ?? 'Member';
@endphp

<div x-data="{ nav: false }" class="min-h-full">
    {{-- Mobile backdrop --}}
    <div x-cloak x-show="nav" x-transition.opacity class="fixed inset-0 z-30 bg-ink-950/80 backdrop-blur-sm lg:hidden" @click="nav = false"></div>

    {{-- ===== Sidebar ===== --}}
    <aside class="fixed inset-y-0 left-0 z-40 flex w-64 -translate-x-full flex-col border-r border-ink-700 bg-ink-900 transition-transform lg:translate-x-0"
           :class="nav && '!translate-x-0'">
        <div class="flex h-16 items-center justify-between gap-3 border-b border-ink-700 px-5">
            <a href="{{ route('dashboard') }}" class="flex items-center gap-2.5">
                <x-application-logo class="h-9 w-9" />
                <div class="leading-tight">
                    <div class="font-display text-sm font-semibold text-ink-100">Global Visioner</div>
                    <div class="text-[10px] font-medium uppercase tracking-[0.25em] text-gold">Education</div>
                </div>
            </a>
            <button type="button" class="text-ink-400 hover:text-ink-100 lg:hidden" @click="nav = false" aria-label="Close menu"><x-icon name="x" /></button>
        </div>

        <nav class="flex-1 space-y-6 overflow-y-auto px-3 py-5">
            @foreach ($sections as $heading => $items)
                @php $visible = array_filter($items, fn ($i) => $i[4]); @endphp
                @if ($visible)
                    <div>
                        <div class="mb-2 px-3 text-[11px] font-semibold uppercase tracking-wider text-ink-400/70">{{ $heading }}</div>
                        <ul class="space-y-0.5">
                            @foreach ($visible as [$label, $route, $icon, $pattern])
                                @php $active = request()->routeIs($pattern); @endphp
                                <li>
                                    <a href="{{ route($route) }}"
                                       @class([
                                           'group flex items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium transition',
                                           'bg-gold/10 text-gold' => $active,
                                           'text-ink-400 hover:bg-ink-800 hover:text-ink-100' => ! $active,
                                       ])
                                       @if ($active) aria-current="page" @endif>
                                        <x-icon :name="$icon" class="h-[18px] w-[18px]" />
                                        {{ $label }}
                                    </a>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endif
            @endforeach
        </nav>

        <div class="border-t border-ink-700 p-3">
            <div class="flex items-center gap-3 rounded-lg px-2 py-2">
                <x-avatar :user="$authUser" />
                <div class="min-w-0 flex-1">
                    <div class="truncate text-sm font-medium text-ink-100">{{ $authUser->name }}</div>
                    <div class="truncate text-xs text-ink-400">{{ $roleLabel }} · {{ $authUser->username }}</div>
                </div>
            </div>
            <div class="mt-1 grid grid-cols-2 gap-1">
                <a href="{{ route('profile.edit') }}" class="flex items-center justify-center gap-1.5 rounded-lg px-2 py-2 text-xs font-medium text-ink-400 hover:bg-ink-800 hover:text-ink-100">
                    <x-icon name="user" class="h-4 w-4" /> Profile
                </a>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button class="flex w-full items-center justify-center gap-1.5 rounded-lg px-2 py-2 text-xs font-medium text-ink-400 hover:bg-bear/10 hover:text-bear-400">
                        <x-icon name="logout" class="h-4 w-4" /> Log out
                    </button>
                </form>
            </div>
        </div>
    </aside>

    {{-- ===== Main ===== --}}
    <div class="lg:pl-64">
        <header class="sticky top-0 z-20 flex h-16 items-center gap-4 border-b border-ink-700 bg-ink-950/85 px-4 backdrop-blur sm:px-6 lg:px-8">
            <button type="button" class="text-ink-400 hover:text-ink-100 lg:hidden" @click="nav = true" aria-label="Open menu"><x-icon name="menu" class="h-6 w-6" /></button>
            <div class="min-w-0 flex-1">
                @if ($title)
                    <h1 class="truncate font-display text-lg font-semibold text-ink-100">{{ $title }}</h1>
                @endif
            </div>
            @isset($actions)
                <div class="flex shrink-0 items-center gap-2">{{ $actions }}</div>
            @endisset
            @if ($isMember)
                {{-- Referral link copy button, visible on every member page (icon-only on phones) --}}
                <div x-data="{ copied: false }" class="shrink-0">
                    <input x-ref="ref" type="text" readonly value="{{ $authUser->referralLink() }}" class="sr-only" tabindex="-1" aria-hidden="true">
                    <button type="button" title="{{ $authUser->referralLink() }}"
                            class="flex items-center gap-2 rounded-lg border border-gold/40 bg-gold/10 px-3 py-1.5 text-sm font-semibold text-gold transition hover:bg-gold/15"
                            @click="
                                const v = $refs.ref.value;
                                (window.isSecureContext && navigator.clipboard ? navigator.clipboard.writeText(v) : Promise.reject())
                                    .catch(() => { $refs.ref.classList.remove('sr-only'); $refs.ref.select(); document.execCommand('copy'); $refs.ref.classList.add('sr-only'); });
                                copied = true; setTimeout(() => copied = false, 1800)">
                        <x-icon x-show="!copied" name="link" class="h-4 w-4" />
                        <x-icon x-show="copied" x-cloak name="check" class="h-4 w-4" />
                        <span class="hidden sm:inline" x-text="copied ? 'Link copied!' : 'Copy referral link'">Copy referral link</span>
                        <span class="sm:hidden" x-show="copied" x-cloak>Copied</span>
                    </button>
                </div>
                <a href="{{ route('wallet.index') }}" class="hidden items-center gap-2 rounded-lg border border-ink-700 bg-ink-900 px-3 py-1.5 text-sm md:flex">
                    <x-icon name="wallet" class="h-4 w-4 text-gold" />
                    <span class="money text-ink-100">${{ number_format((float) $authUser->wallet_balance, 2) }}</span>
                </a>
            @endif
        </header>

        <main class="px-4 py-6 sm:px-6 lg:px-8 lg:py-8">
            @if ($subtitle)
                <p class="-mt-1 mb-6 text-sm text-ink-400">{{ $subtitle }}</p>
            @endif

            @if (session('success'))
                <div x-data="{ show: true }" x-show="show" class="mb-6 flex items-start gap-3 rounded-xl border border-bull/30 bg-bull/10 px-4 py-3 text-sm text-bull-400" role="status">
                    <x-icon name="check" class="mt-0.5 h-4 w-4" />
                    <span class="flex-1">{{ session('success') }}</span>
                    <button type="button" @click="show = false" class="text-bull-400/70 hover:text-bull-400" aria-label="Dismiss"><x-icon name="x" class="h-4 w-4" /></button>
                </div>
            @endif

            @if ($errors->any())
                <div class="mb-6 rounded-xl border border-bear/30 bg-bear/10 px-4 py-3 text-sm text-bear-400" role="alert">
                    <p class="font-medium">Please fix the following:</p>
                    <ul class="mt-1 list-inside list-disc space-y-0.5">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            {{ $slot }}
        </main>
    </div>
</div>
</body>
</html>
