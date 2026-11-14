<?php

namespace App\Support;

use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;

/**
 * Reads an uploaded image into a GD resource.
 *
 * Uses getPathname() (the temp path PHP gave us), not getRealPath(): on Windows/Apache
 * realpath() can return false for the upload temp folder even though the file is readable,
 * which made file_get_contents('') throw "Path cannot be empty".
 */
class UploadReader
{
    /** 50 megapixels covers every phone camera; anything bigger is refused before decoding. */
    public const MAX_PIXELS = 50_000_000;

    public const MAX_SIDE = 12_000;

    private const MEMORY_NEEDED = 512 * 1024 * 1024;

    /** "128M" -> bytes; -1 (unlimited) counts as enough. */
    private static function bytes(string $limit): int
    {
        if ($limit === '' || $limit === '-1') {
            return PHP_INT_MAX;
        }
        $value = (int) $limit;

        return match (strtolower(substr($limit, -1))) {
            'g' => $value * 1024 ** 3,
            'm' => $value * 1024 ** 2,
            'k' => $value * 1024,
            default => $value,
        };
    }

    public static function image(UploadedFile $file, string $field): \GdImage
    {
        $path = $file->getPathname();
        $bytes = ($path !== '' && is_readable($path)) ? file_get_contents($path) : false;

        if ($bytes === false || $bytes === '') {
            throw ValidationException::withMessages([$field => 'The image could not be uploaded. Please try again with a JPG, PNG or WEBP file.']);
        }

        // Check dimensions before decoding: a small file can still be a huge image
        // (e.g. a 20000×20000 PNG needs ~1.6 GB of memory and would crash the request).
        $size = @getimagesizefromstring($bytes);
        if ($size === false || $size[0] < 1 || $size[1] < 1) {
            throw ValidationException::withMessages([$field => 'This image could not be read. Please upload a JPG, PNG or WEBP file.']);
        }
        if ($size[0] > self::MAX_SIDE || $size[1] > self::MAX_SIDE || $size[0] * $size[1] > self::MAX_PIXELS) {
            throw ValidationException::withMessages([$field => 'This image is too large. Please upload a screenshot or a smaller photo.']);
        }

        // Decoding a large phone photo needs ~4 bytes per pixel; raise a low memory limit for this request.
        if (self::bytes((string) ini_get('memory_limit')) < self::MEMORY_NEEDED) {
            @ini_set('memory_limit', '512M');
        }

        $image = @imagecreatefromstring($bytes);
        if ($image === false) {
            throw ValidationException::withMessages([$field => 'This image could not be read. Please upload a JPG, PNG or WEBP file.']);
        }

        return $image;
    }
}
