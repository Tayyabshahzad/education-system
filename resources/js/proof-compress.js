/**
 * Shrinks a payment screenshot in the browser before it is uploaded, so phones on slow
 * connections send ~100 KB instead of several MB. The server re-compresses anyway
 * (ProofImageService), so if anything here fails the original file is simply sent.
 */
// Same limits as ProofImageService: short side ≤ 800px, long side ≤ 1600px.
const MAX_SHORT_SIDE = 800;
const MAX_LONG_SIDE = 1600;
const QUALITY = 0.6;

function formatSize(bytes) {
    return bytes > 1024 * 1024 ? (bytes / 1024 / 1024).toFixed(1) + ' MB' : Math.max(1, Math.round(bytes / 1024)) + ' KB';
}

async function compress(file) {
    const bitmap = await createImageBitmap(file);
    const scale = Math.min(
        1,
        MAX_SHORT_SIDE / Math.min(bitmap.width, bitmap.height),
        MAX_LONG_SIDE / Math.max(bitmap.width, bitmap.height),
    );
    const canvas = document.createElement('canvas');
    canvas.width = Math.round(bitmap.width * scale);
    canvas.height = Math.round(bitmap.height * scale);
    const ctx = canvas.getContext('2d');
    ctx.fillStyle = '#fff';
    ctx.fillRect(0, 0, canvas.width, canvas.height);
    ctx.drawImage(bitmap, 0, 0, canvas.width, canvas.height);
    bitmap.close?.();

    const blob = await new Promise((resolve) => canvas.toBlob(resolve, 'image/jpeg', QUALITY));
    if (!blob || blob.size >= file.size) {
        return file; // already small; keep original
    }
    return new File([blob], file.name.replace(/\.\w+$/, '') + '.jpg', { type: 'image/jpeg' });
}

export default function proofUpload() {
    return {
        preview: null,
        name: '',
        info: '',
        async pick(event) {
            const input = event.target;
            const original = input.files?.[0];
            if (!original) {
                this.preview = null;
                return;
            }

            let file = original;
            try {
                file = await compress(original);
                if (file !== original) {
                    const transfer = new DataTransfer();
                    transfer.items.add(file);
                    input.files = transfer.files;
                }
            } catch (e) {
                file = original; // unsupported browser: upload as-is, server still compresses
            }

            if (this.preview) URL.revokeObjectURL(this.preview);
            this.preview = URL.createObjectURL(file);
            this.name = original.name;
            this.info = file === original ? formatSize(file.size) : `${formatSize(original.size)} → ${formatSize(file.size)}`;
        },
    };
}
