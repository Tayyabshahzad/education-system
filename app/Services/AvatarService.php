<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/** Profile pictures: centre-cropped to a square and stored as a small WebP (~10–25 KB). */
class AvatarService
{
    public const SIZE = 320;

    public const QUALITY = 70;

    public function update(User $user, UploadedFile $file): void
    {
        $source = @imagecreatefromstring((string) file_get_contents($file->getRealPath()));
        if ($source === false) {
            throw ValidationException::withMessages(['avatar' => 'This image could not be read. Please upload a JPG, PNG or WEBP photo.']);
        }

        $width = imagesx($source);
        $height = imagesy($source);
        $side = min($width, $height);
        $target = min(self::SIZE, $side);

        $canvas = imagecreatetruecolor($target, $target);
        imagefill($canvas, 0, 0, imagecolorallocate($canvas, 255, 255, 255));
        imagecopyresampled($canvas, $source, 0, 0, (int) (($width - $side) / 2), (int) (($height - $side) / 2), $target, $target, $side, $side);

        ob_start();
        function_exists('imagewebp') ? imagewebp($canvas, null, self::QUALITY) : imagejpeg($canvas, null, self::QUALITY);
        $bytes = (string) ob_get_clean();

        $path = 'avatars/'.$user->id.'-'.Str::random(12).(function_exists('imagewebp') ? '.webp' : '.jpg');
        Storage::put($path, $bytes);

        $this->deleteFile($user);
        $user->forceFill(['avatar_path' => $path])->save();
    }

    public function remove(User $user): void
    {
        $this->deleteFile($user);
        $user->forceFill(['avatar_path' => null])->save();
    }

    private function deleteFile(User $user): void
    {
        if ($user->avatar_path) {
            Storage::delete($user->avatar_path);
        }
    }
}
