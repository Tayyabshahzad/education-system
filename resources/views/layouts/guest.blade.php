<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#05080F">

    <title>{{ $title ? $title.' · ' : '' }}{{ config('app.name') }}</title>

    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700|space-grotesk:500,600,700|jetbrains-mono:500&display=swap" rel="stylesheet" />

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="h-full font-sans antialiased">
@php
    // Deterministic decorative candlestick series for the brand panel.
    mt_srand(11);
    $candles = [];
    $price = 160;
    for ($i = 0; $i < 34; $i++) {
        $open = $price;
        $close = $open + mt_rand(-14, 17);
        $high = max($open, $close) + mt_rand(2, 9);
        $low = min($open, $close) - mt_rand(2, 9);
        $candles[] = compact('open', 'close', 'high', 'low');
        $price = max(60, min(250, $close));
    }
    mt_srand();

    $w = 560;
    $h = 190;
    $step = $w / count($candles);
    $max = max(array_column($candles, 'high'));
    $min = min(array_column($candles, 'low'));
    $y = fn ($v) => round($h - (($v - $min) / ($max - $min)) * ($h - 10) - 5, 1);
    $ma = collect($candles)
        ->map(fn ($c, $i) => round($i * $step + $step / 2, 1).','.$y(($c['open'] + $c['close']) / 2))
        ->implode(' ');

    $pairs = ['EUR/USD', 'GBP/USD', 'USD/JPY', 'XAU/USD', 'AUD/USD', 'USD/CAD', 'USD/CHF', 'NZD/USD', 'EUR/JPY', 'GBP/JPY'];
    $features = [
        ['Price Action', 'Chart reading & setups'],
        ['Risk Control', 'Position sizing & stop loss'],
        ['Live Sessions', 'Trade alongside mentors'],
    ];
@endphp

<div class="min-h-full lg:grid lg:grid-cols-[1.05fr_1fr]">

    {{-- ===== Brand panel (desktop) ===== --}}
    <aside class="relative hidden lg:flex flex-col overflow-hidden border-r border-ink-700 bg-ink-900">
        <div class="absolute inset-0 grid-bg"></div>
        <div class="absolute -top-40 -left-40 h-96 w-96 rounded-full bg-gold/10 blur-3xl"></div>
        <div class="absolute bottom-0 right-0 h-80 w-80 rounded-full bg-bull/10 blur-3xl"></div>

        {{-- Currency pairs ticker --}}
        <div class="relative z-10 overflow-hidden border-b border-ink-700 bg-ink-950/60 backdrop-blur">
            <div class="flex w-max animate-ticker">
                @foreach (array_merge($pairs, $pairs) as $i => $pair)
                    <span class="flex items-center gap-2 px-6 py-2.5 font-mono text-xs tracking-wide text-ink-400">
                        <span class="h-1.5 w-1.5 rounded-full {{ $i % 3 === 1 ? 'bg-bear' : 'bg-bull' }}"></span>
                        {{ $pair }}
                    </span>
                @endforeach
            </div>
        </div>

        <div class="relative z-10 flex flex-1 flex-col justify-between gap-10 p-12 xl:p-16">
            <a href="/" class="flex items-center gap-3">
                <x-application-logo class="h-11 w-11" />
                <div class="leading-tight">
                    <div class="font-display text-lg font-semibold text-white">Global Visioner</div>
                    <div class="text-xs font-medium uppercase tracking-[0.25em] text-gold">Education</div>
                </div>
            </a>

            <div>
                <p class="mb-4 inline-flex items-center gap-2 rounded-full border border-ink-700 bg-ink-800/70 px-3 py-1 text-xs font-medium text-ink-200">
                    <span class="h-1.5 w-1.5 animate-pulse rounded-full bg-bull"></span>
                    Forex Trading Academy
                </p>
                <h1 class="font-display text-4xl font-semibold leading-tight text-white xl:text-5xl">
                    Read the market.<br>
                    <span class="text-gradient-gold">Trade with a plan.</span>
                </h1>
                <p class="mt-5 max-w-md text-ink-400">
                    Structured forex training from fundamentals to live market execution. Learn price action,
                    risk management and trading psychology step by step.
                </p>

                {{-- Decorative candlestick chart --}}
                <div class="mt-10 rounded-2xl border border-ink-700 bg-ink-950/70 p-5 backdrop-blur">
                    <div class="mb-3 flex items-center justify-between font-mono text-xs text-ink-400">
                        <span class="text-ink-200">XAU/USD · H4</span>
                        <span class="flex gap-3"><span>1H</span><span class="text-gold">4H</span><span>1D</span></span>
                    </div>
                    <svg viewBox="0 0 {{ $w }} {{ $h }}" class="h-auto w-full" aria-hidden="true">
                        @foreach ([0.25, 0.5, 0.75] as $g)
                            <line x1="0" x2="{{ $w }}" y1="{{ $h * $g }}" y2="{{ $h * $g }}" stroke="#1A2338" stroke-dasharray="3 5"/>
                        @endforeach
                        @foreach ($candles as $i => $c)
                            @php
                                $color = $c['close'] >= $c['open'] ? '#16C784' : '#EA3943';
                                $cx = $i * $step + $step / 2;
                                $top = $y(max($c['open'], $c['close']));
                                $bh = max(1.5, $y(min($c['open'], $c['close'])) - $top);
                            @endphp
                            <line x1="{{ $cx }}" x2="{{ $cx }}" y1="{{ $y($c['high']) }}" y2="{{ $y($c['low']) }}" stroke="{{ $color }}" stroke-width="1.2"/>
                            <rect x="{{ $cx - $step * 0.3 }}" y="{{ $top }}" width="{{ $step * 0.6 }}" height="{{ $bh }}" rx="1" fill="{{ $color }}"/>
                        @endforeach
                        <polyline points="{{ $ma }}" fill="none" stroke="#F5B942" stroke-width="1.6" stroke-dasharray="1200" class="animate-draw" opacity=".9"/>
                    </svg>
                </div>
            </div>

            <ul class="grid grid-cols-3 gap-4 text-sm">
                @foreach ($features as [$featureTitle, $featureText])
                    <li class="rounded-xl border border-ink-700 bg-ink-800/50 p-4">
                        <div class="font-medium text-white">{{ $featureTitle }}</div>
                        <div class="mt-1 text-xs text-ink-400">{{ $featureText }}</div>
                    </li>
                @endforeach
            </ul>
        </div>
    </aside>

    {{-- ===== Form panel ===== --}}
    <main class="relative flex min-h-screen flex-col">
        <div class="absolute inset-0 grid-bg opacity-60 lg:hidden"></div>

        <header class="relative z-10 flex items-center px-6 py-5 lg:hidden">
            <a href="/" class="flex items-center gap-2.5">
                <x-application-logo class="h-9 w-9" />
                <span class="font-display font-semibold text-white">Global Visioner <span class="text-gold">Education</span></span>
            </a>
        </header>

        <div class="relative z-10 flex flex-1 items-center justify-center px-4 py-10 sm:px-8">
            <div class="w-full {{ $width }}">
                {{ $slot }}
            </div>
        </div>

        <footer class="relative z-10 px-6 py-5 text-center text-xs text-ink-400">
            &copy; {{ date('Y') }} Global Visioner Education. Trading involves risk; all training is for educational purposes only.
        </footer>
    </main>
</div>
</body>
</html>
