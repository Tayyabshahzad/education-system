{{--
    Payment screenshot input. The image is shrunk in the browser before upload
    (resources/js/proof-compress.js) and re-compressed on the server (ProofImageService).
--}}
@props(['required' => true, 'label' => 'Payment screenshot'])
<div x-data="proofUpload()" {{ $attributes }}>
    <x-input-label for="proof" :value="$label" />
    <label for="proof"
           class="flex cursor-pointer flex-col items-center justify-center gap-2 rounded-xl border border-dashed px-4 py-5 text-center transition"
           :class="preview ? 'border-gold/50 bg-gold/5' : 'border-ink-600 bg-ink-800/50 hover:border-gold/40'">
        <template x-if="!preview">
            <span class="flex flex-col items-center gap-2">
                <x-icon name="upload" class="h-6 w-6 text-gold" />
                <span class="text-sm font-medium text-white">Tap to attach screenshot</span>
                <span class="text-xs text-ink-400">JPG, PNG or WEBP · it is compressed automatically</span>
            </span>
        </template>
        <template x-if="preview">
            <span class="flex w-full items-center gap-3 text-left">
                <img :src="preview" alt="Payment screenshot preview" class="h-16 w-16 rounded-lg object-cover ring-1 ring-ink-600">
                <span class="min-w-0 flex-1">
                    <span class="block truncate text-sm font-medium text-white" x-text="name"></span>
                    <span class="block text-xs text-ink-400" x-text="info"></span>
                    <span class="mt-0.5 block text-xs text-gold">Tap to change</span>
                </span>
            </span>
        </template>
    </label>
    <input id="proof" name="proof" type="file" accept="image/jpeg,image/png,image/webp" class="sr-only" @change="pick($event)" @if ($required) required @endif>
    <x-input-error :messages="$errors->get('proof')" />
</div>
