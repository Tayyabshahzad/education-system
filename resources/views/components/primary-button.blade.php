<button {{ $attributes->merge(['type' => 'submit', 'class' => 'inline-flex items-center justify-center gap-2 rounded-lg bg-gold px-5 py-2.5 text-sm font-semibold text-white shadow-lg shadow-gold/20 transition hover:bg-gold-400 focus:outline-none focus:ring-2 focus:ring-gold/60 focus:ring-offset-2 focus:ring-offset-ink-950 active:bg-gold-600 disabled:opacity-60']) }}>
    {{ $slot }}
</button>
