<?php

namespace App\Services;

use App\Support\UploadReader;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Stores payment screenshots as small, text-readable images to keep server storage low.
 *
 * Every upload is re-encoded (never stored as-is):
 *  1. scaled so the short side is at most MAX_SHORT_SIDE and the long side at most
 *     MAX_LONG_SIDE (a 1440×3120 phone screenshot becomes 720×1560, text stays sharp),
 *  2. saved as WebP (JPEG if WebP is unavailable) at a low quality,
 *  3. if still above MAX_BYTES (noisy camera photos), quality is lowered and then the
 *     image is shrunk step by step, but the short side never goes below MIN_SHORT_SIDE.
 *
 * A normal phone screenshot ends up around 30–120 KB; the worst case is about MAX_BYTES.
 */
class ProofImageService
{
    public const MAX_SHORT_SIDE = 800;

    public const MAX_LONG_SIDE = 1600;

    public const MIN_SHORT_SIDE = 600;

    public const MAX_BYTES = 250 * 1024;

    /** Tried in order until the file fits under MAX_BYTES. */
    public const QUALITIES = [50, 38, 28];

    public const DIRECTORY = 'payment-proofs';

    /** @param  string  $directory  payment-proofs (member fees) or payout-proofs (admin withdrawal receipts) */
    public function store(UploadedFile $file, string $directory = self::DIRECTORY): string
    {
        $source = UploadReader::image($file, 'proof');

        $useWebp = function_exists('imagewebp');
        $bytes = $this->compress($source, $useWebp);
        imagedestroy($source);

        $path = $directory.'/'.now()->format('Y/m').'/'.Str::random(32).($useWebp ? '.webp' : '.jpg');
        Storage::put($path, $bytes);

        return $path;
    }

    private function compress(\GdImage $source, bool $useWebp): string
    {
        $width = imagesx($source);
        $height = imagesy($source);
        $scale = min(1, self::MAX_SHORT_SIDE / min($width, $height), self::MAX_LONG_SIDE / max($width, $height));
        $minScale = min($scale, self::MIN_SHORT_SIDE / min($width, $height));

        while (true) {
            $image = $this->resize($source, $scale);

            foreach (self::QUALITIES as $quality) {
                $bytes = $this->encode($image, $useWebp, $quality);
                if (strlen($bytes) <= self::MAX_BYTES) {
                    imagedestroy($image);

                    return $bytes;
                }
            }
            imagedestroy($image);

            // Still too big at the lowest quality: shrink, but keep text readable.
            if ($scale <= $minScale) {
                return $bytes;
            }
            $scale = max($minScale, $scale * 0.85);
        }
    }

    private function encode(\GdImage $image, bool $useWebp, int $quality): string
    {
        ob_start();
        // JPEG needs a slightly higher number for similar visual quality.
        $useWebp ? imagewebp($image, null, $quality) : imagejpeg($image, null, min(100, $quality + 5));

        return (string) ob_get_clean();
    }

    private function resize(\GdImage $source, float $scale): \GdImage
    {
        $width = imagesx($source);
        $height = imagesy($source);
        $newWidth = max(1, (int) round($width * $scale));
        $newHeight = max(1, (int) round($height * $scale));

        // Always copy onto a fresh white canvas: flattens PNG transparency and drops metadata.
        $canvas = imagecreatetruecolor($newWidth, $newHeight);
        imagefill($canvas, 0, 0, imagecolorallocate($canvas, 255, 255, 255));
        imagecopyresampled($canvas, $source, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);

        return $canvas;
    }
}
