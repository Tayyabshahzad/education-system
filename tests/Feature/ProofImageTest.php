<?php

namespace Tests\Feature;

use App\Services\ProofImageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ProofImageTest extends TestCase
{
    use RefreshDatabase;

    /** A noisy, photo-like PNG that compresses badly — the worst case for storage. */
    private function noisyScreenshot(int $width, int $height): UploadedFile
    {
        $img = imagecreatetruecolor($width, $height);
        for ($y = 0; $y < $height; $y += 4) {
            for ($x = 0; $x < $width; $x += 4) {
                imagefilledrectangle($img, $x, $y, $x + 3, $y + 3, imagecolorallocate($img, random_int(0, 255), random_int(0, 255), random_int(0, 255)));
            }
        }
        imagestring($img, 5, 40, 40, 'TRANSACTION ID 123456 AMOUNT $200', imagecolorallocate($img, 0, 0, 0));

        $path = tempnam(sys_get_temp_dir(), 'proof').'.png';
        imagepng($img, $path);
        imagedestroy($img);

        return new UploadedFile($path, 'screenshot.png', 'image/png', null, true);
    }

    /** A typical banking-app screenshot: flat background, coloured header, lines of text. */
    private function appScreenshot(int $width, int $height): UploadedFile
    {
        $img = imagecreatetruecolor($width, $height);
        imagefill($img, 0, 0, imagecolorallocate($img, 245, 247, 250));
        imagefilledrectangle($img, 0, 0, $width, 260, imagecolorallocate($img, 22, 163, 74));
        $ink = imagecolorallocate($img, 20, 20, 20);
        for ($y = 320; $y < $height - 60; $y += 70) {
            imagestring($img, 5, 60, $y, 'Transaction ID 9812736451  Amount USD 200.00  Status Successful', $ink);
        }

        $path = tempnam(sys_get_temp_dir(), 'proof').'.png';
        imagepng($img, $path);
        imagedestroy($img);

        return new UploadedFile($path, 'screenshot.png', 'image/png', null, true);
    }

    public function test_phone_screenshot_is_resized_and_becomes_tiny(): void
    {
        Storage::fake();
        $upload = $this->appScreenshot(1440, 3120);

        $path = app(ProofImageService::class)->store($upload);

        Storage::assertExists($path);
        $this->assertStringEndsWith('.webp', $path);

        $stored = Storage::get($path);
        [$width, $height] = getimagesizefromstring($stored);
        $this->assertSame([738, 1600], [$width, $height], 'Long side capped at 1600, aspect kept (scale 0.513)');
        $this->assertLessThan(120 * 1024, strlen($stored), 'Screenshot stored well under 120 KB');
    }

    public function test_wide_camera_photo_long_side_is_capped(): void
    {
        Storage::fake();

        $path = app(ProofImageService::class)->store(UploadedFile::fake()->image('photo.jpg', 4000, 1500));

        [$width, $height] = getimagesizefromstring(Storage::get($path));
        $this->assertSame([ProofImageService::MAX_LONG_SIDE, 600], [$width, $height]);
    }

    public function test_noisy_camera_photo_is_squeezed_under_the_byte_limit(): void
    {
        Storage::fake();
        $upload = $this->noisyScreenshot(2400, 3600);

        $path = app(ProofImageService::class)->store($upload);

        $stored = Storage::get($path);
        [$width, $height] = getimagesizefromstring($stored);
        $this->assertLessThanOrEqual(ProofImageService::MAX_BYTES, strlen($stored));
        $this->assertGreaterThanOrEqual(ProofImageService::MIN_SHORT_SIDE, min($width, $height), 'Never shrunk below readable size');
        $this->assertLessThan($upload->getSize() / 5, strlen($stored));
    }

    public function test_small_screenshot_keeps_its_size_but_is_still_reencoded(): void
    {
        Storage::fake();

        $path = app(ProofImageService::class)->store(UploadedFile::fake()->image('small.jpg', 600, 400));

        [$width, $height] = getimagesizefromstring(Storage::get($path));
        $this->assertSame([600, 400], [$width, $height]);
    }

    public function test_non_image_content_is_rejected(): void
    {
        Storage::fake();
        $this->expectException(ValidationException::class);

        app(ProofImageService::class)->store(UploadedFile::fake()->createWithContent('fake.jpg', 'not really an image'));
    }

    public function test_image_with_huge_dimensions_is_refused_before_decoding(): void
    {
        $img = imagecreatetruecolor(13000, 50);
        $path = tempnam(sys_get_temp_dir(), 'huge').'.png';
        imagepng($img, $path);
        imagedestroy($img);

        $this->expectException(ValidationException::class);
        app(ProofImageService::class)->store(new UploadedFile($path, 'huge.png', 'image/png', null, true));
    }
}
