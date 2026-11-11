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
    public static function image(UploadedFile $file, string $field): \GdImage
    {
        $path = $file->getPathname();
        $bytes = ($path !== '' && is_readable($path)) ? file_get_contents($path) : false;

        if ($bytes === false || $bytes === '') {
            throw ValidationException::withMessages([$field => 'The image could not be uploaded. Please try again with a JPG, PNG or WEBP file.']);
        }

        $image = @imagecreatefromstring($bytes);
        if ($image === false) {
            throw ValidationException::withMessages([$field => 'This image could not be read. Please upload a JPG, PNG or WEBP file.']);
        }

        return $image;
    }
}
