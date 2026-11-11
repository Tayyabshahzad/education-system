@props(['disabled' => false])

<input @disabled($disabled) {{ $attributes->merge(['class' => 'block w-full rounded-lg border-ink-700 bg-ink-800 px-3.5 py-2.5 text-sm text-white placeholder-ink-400/70 shadow-sm transition focus:border-gold focus:ring-gold/40 disabled:opacity-60']) }}>
