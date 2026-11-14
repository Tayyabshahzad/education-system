{{--
    Monthly metrics card: KPI tabs on top, a bar chart of the selected metric, and a table
    of every metric side by side.
    Expects: $chart (ReportService::personalChart / businessChart), $title, optional $subtitle, $class.

    One metric is charted at a time on its own axis (money and head-counts never share
    an axis). Plain HTML bars: one hue, rounded tops, recessive grid, hover/focus tooltips.
--}}
@php
    $months = $chart['months'];
    $fmt = function (float $v, string $format) {
        if ($format === 'count') {
            return number_format($v);
        }

        return '$'.number_format($v, $v == floor($v) ? 0 : 2);
    };

    $metrics = collect($chart['metrics'])->map(function (array $m) use ($fmt) {
        $values = array_column($m['values'], 'value');
        $n = count($values);
        $current = $values[$n - 1];
        $previous = $values[$n - 2] ?? 0;

        // "Nice" axis maximum with 4 equal steps (1, 2, 2.5 or 5 × 10^n per step).
        $max = max($values) ?: 0;
        $rawStep = $max > 0 ? $max / 4 : ($m['format'] === 'count' ? 1 : 25);
        $magnitude = 10 ** floor(log10($rawStep));
        $step = collect([1, 2, 2.5, 5, 10])->map(fn ($x) => $x * $magnitude)->first(fn ($s) => $s >= $rawStep);
        if ($m['format'] === 'count') {
            $step = max(1, ceil($step));
        }

        return $m + [
            'total' => array_sum($values),
            'current' => $current,
            'change' => $previous > 0 ? (int) round(($current - $previous) / $previous * 100) : null,
            'axisMax' => $step * 4,
            'ticks' => [0, $step, $step * 2, $step * 3, $step * 4],
        ];
    });
    $first = $metrics->keys()->first();
@endphp
<div x-data="{ metric: @js($first), table: false }" @class(['card', $class ?? ''])>
    <div class="card-header">
        <div>
            <h3 class="card-title">{{ $title }}</h3>
            <p class="mt-0.5 text-xs text-ink-400">{{ $subtitle ?? 'Last 12 months, month by month' }}</p>
        </div>
        <div class="flex overflow-hidden rounded-md border border-ink-700 text-xs" role="group" aria-label="View">
            <button type="button" @click="table = false" :class="!table ? 'bg-ink-800 text-ink-100' : 'text-ink-400'" class="px-2.5 py-1">Chart</button>
            <button type="button" @click="table = true" :class="table ? 'bg-ink-800 text-ink-100' : 'text-ink-400'" class="border-l border-ink-700 px-2.5 py-1">Table</button>
        </div>
    </div>

    {{-- KPI tabs: 12-month total per metric; click to chart it --}}
    <div @class(['grid border-b border-ink-700', 'grid-cols-2 sm:grid-cols-4' => $metrics->count() === 4, 'grid-cols-3' => $metrics->count() === 3])
         role="tablist" aria-label="{{ $title }} metric">
        @foreach ($metrics as $key => $m)
            <button type="button" role="tab" @click="metric = @js($key); table = false" :aria-selected="(metric === @js($key)).toString()"
                    class="relative border-ink-700 px-4 py-3.5 text-left transition hover:bg-ink-800/40 [&:not(:last-child)]:border-r"
                    :class="metric === @js($key) && !table ? 'bg-ink-800/50' : ''">
                <span class="absolute inset-x-0 top-0 h-0.5 bg-gold transition-opacity" :class="metric === @js($key) && !table ? 'opacity-100' : 'opacity-0'"></span>
                <span class="block text-xs text-ink-400">{{ $m['label'] }}</span>
                <span class="money mt-1 block font-display text-lg font-semibold text-ink-100 sm:text-xl">{{ $fmt($m['total'], $m['format']) }}</span>
                <span class="mt-0.5 block text-[11px] text-ink-400">
                    This month <span class="money text-ink-200">{{ $fmt($m['current'], $m['format']) }}</span>
                    @if ($m['change'] !== null)
                        <span @class(['ml-0.5 whitespace-nowrap', 'text-bull-400' => $m['change'] >= 0, 'text-bear-400' => $m['change'] < 0])>{{ $m['change'] >= 0 ? '▲' : '▼' }}{{ abs($m['change']) }}%</span>
                    @endif
                </span>
            </button>
        @endforeach
    </div>

    <div class="card-body">
        {{-- One chart per metric; only the selected one is shown --}}
        @foreach ($metrics as $key => $m)
            <div x-show="!table && metric === @js($key)" @if (! $loop->first) x-cloak @endif role="tabpanel">
                <div class="relative h-56" role="img" aria-label="{{ $m['label'] }} per month for the last 12 months, total {{ $fmt($m['total'], $m['format']) }}">
                    @foreach ($m['ticks'] as $tick)
                        <div class="pointer-events-none absolute inset-x-0 flex items-center" style="bottom: {{ $tick / $m['axisMax'] * 100 }}%">
                            <span class="money w-11 shrink-0 pr-2 text-right text-[11px] leading-none text-ink-400">{{ $fmt($tick, $m['format']) }}</span>
                            <span @class(['h-px flex-1', 'bg-ink-600' => $tick == 0, 'bg-ink-700/50' => $tick != 0])></span>
                        </div>
                    @endforeach

                    <div class="absolute inset-y-0 left-11 right-0 flex items-end gap-1 sm:gap-2">
                        @foreach ($m['values'] as $i => $point)
                            @php $pct = $point['value'] / $m['axisMax'] * 100; @endphp
                            <div class="group relative flex h-full flex-1 items-end justify-center rounded-md outline-none hover:bg-ink-800/60 focus-visible:bg-ink-800/60 focus-visible:ring-1 focus-visible:ring-gold/50"
                                 tabindex="0" aria-label="{{ $months[$i]['full'] }}: {{ $fmt($point['value'], $m['format']) }}">
                                <div class="w-full max-w-[28px] rounded-t bg-gold transition-opacity group-hover:opacity-90" style="height: {{ $pct }}%"></div>
                                <div class="pointer-events-none absolute z-10 hidden -translate-x-1/2 whitespace-nowrap rounded-lg border border-ink-600 bg-ink-950 px-3 py-2 text-xs shadow-xl group-hover:block group-focus-visible:block"
                                     style="bottom: calc({{ min($pct, 75) }}% + 8px); left: 50%">
                                    <div class="font-medium text-ink-100">{{ $months[$i]['full'] }}</div>
                                    <div class="text-ink-400">{{ $m['label'] }}</div>
                                    <div class="money mt-0.5 text-sm text-ink-100">{{ $fmt($point['value'], $m['format']) }}</div>
                                    @if ($m['format'] === 'money')
                                        <div class="text-ink-400">{{ $point['count'] }} {{ Str::plural($m['unit'], $point['count']) }}</div>
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>

                <div class="ml-11 mt-2 flex gap-1 sm:gap-2">
                    @foreach ($months as $i => $month)
                        <div @class(['flex-1 text-center text-[11px] text-ink-400', 'invisible sm:visible' => (count($months) - 1 - $i) % 2 === 1])>{{ $month['label'] }}</div>
                    @endforeach
                </div>

                @if ($m['total'] == 0)
                    <p class="mt-4 text-center text-sm text-ink-400">Nothing recorded for {{ strtolower($m['label']) }} in the last 12 months yet.</p>
                @endif
            </div>
        @endforeach

        {{-- Every metric side by side --}}
        <div x-show="table" x-cloak class="table-wrap -mx-5 -my-5">
            <table class="table">
                <thead>
                    <tr>
                        <th>Month</th>
                        @foreach ($metrics as $m)
                            <th class="text-right">{{ $m['label'] }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @foreach (array_reverse(array_keys($months)) as $i)
                        <tr>
                            <td>{{ $months[$i]['full'] }}</td>
                            @foreach ($metrics as $m)
                                <td class="money text-right text-ink-100">{{ $fmt($m['values'][$i]['value'], $m['format']) }}</td>
                            @endforeach
                        </tr>
                    @endforeach
                    <tr class="font-semibold">
                        <td class="text-ink-100">Total</td>
                        @foreach ($metrics as $m)
                            <td class="money text-right text-gold">{{ $fmt($m['total'], $m['format']) }}</td>
                        @endforeach
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</div>
