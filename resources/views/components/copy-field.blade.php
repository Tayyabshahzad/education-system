@props(['value', 'label' => 'Copy'])
<div x-data="{ copied: false }" {{ $attributes->merge(['class' => 'flex items-stretch overflow-hidden rounded-lg border border-ink-700 bg-ink-800']) }}>
    <input x-ref="field" type="text" readonly value="{{ $value }}" class="min-w-0 flex-1 border-0 bg-transparent px-3.5 py-2.5 font-mono text-xs text-ink-200 focus:ring-0" aria-label="{{ $label }}" onclick="this.select()">
    <button type="button" class="flex items-center gap-1.5 border-l border-ink-700 px-3.5 text-xs font-semibold text-gold hover:bg-ink-700/50"
            @click="
                $refs.field.select();
                (window.isSecureContext && navigator.clipboard ? navigator.clipboard.writeText($refs.field.value) : Promise.reject())
                    .catch(() => document.execCommand('copy'));
                copied = true; setTimeout(() => copied = false, 1600)">
        <x-icon name="copy" class="h-4 w-4" />
        <span x-text="copied ? 'Copied' : @js($label)">{{ $label }}</span>
    </button>
</div>
