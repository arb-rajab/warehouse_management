<?php

use App\Models\Upload;
use App\Services\ImageOptimizer;
use App\Services\MultipartUploadService;
use App\Services\ShopVerificationStorage;
use App\Services\TradeLicenseStorage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

test('the optimizer bounds dimensions and bytes without enlarging images', function (string $format, int $width, int $height, int $expectedWidth, int $expectedHeight) {
    $file = UploadedFile::fake()->image('photo.'.$format, $width, $height);
    [$bytes, $extension] = app(ImageOptimizer::class)->optimise($file->getContent());
    $info = getimagesizefromstring($bytes);
    expect($extension)->toBe($format)->and($info[0])->toBe($expectedWidth)
        ->and($info[1])->toBe($expectedHeight)->and(strlen($bytes))->toBeLessThanOrEqual(ImageOptimizer::MAX_OUTPUT_BYTES);
})->with([
    ['jpg', 3000, 2000, 1500, 1000], ['png', 2000, 3000, 1000, 1500],
    ['png', 2000, 2000, 1500, 1500], ['jpg', 80, 60, 80, 60],
]);

test('a resized image keeps its aspect ratio and is encoded in the requested format', function (string $source, int $width, int $height, ?string $format, int $expectedWidth, int $expectedHeight) {
    $file = UploadedFile::fake()->image('photo.'.$source, $width, $height);
    [$bytes, $extension] = app(ImageOptimizer::class)->optimise($file->getContent(), $format);
    $info = getimagesizefromstring($bytes);
    $expected = $format === 'webp' && ! function_exists('imagewebp') ? $source : ($format ?? $source);
    $mimes = ['jpg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp', 'gif' => 'image/gif'];
    expect($extension)->toBe($expected)->and($info['mime'])->toBe($mimes[$expected])
        ->and([$info[0], $info[1]])->toBe([$expectedWidth, $expectedHeight]);
})->with([
    'png to jpeg' => ['png', 2400, 1200, 'jpg', 1500, 750],
    'jpeg to webp' => ['jpg', 1200, 2400, 'webp', 750, 1500],
    'gif stays gif' => ['gif', 3000, 1500, null, 1500, 750],
    'the short side is rounded, not truncated' => ['png', 1000, 3001, null, 500, 1500],
]);

test('every EXIF orientation is applied the way the tag describes', function (int $orientation, int $width, int $height, string $redCorner) {
    $source = imagecreatetruecolor(80, 40);
    imagefill($source, 0, 0, imagecolorallocate($source, 0, 0, 255));
    imagefilledrectangle($source, 0, 0, 39, 19, imagecolorallocate($source, 255, 0, 0));
    ob_start();
    imagejpeg($source, null, 100);
    $jpeg = (string) ob_get_clean();
    $exif = "Exif\0\0II\x2a\0\x08\0\0\0\x01\0\x12\x01\x03\0\x01\0\0\0".chr($orientation)."\0\0\0\0\0\0\0";
    $jpeg = substr($jpeg, 0, 2)."\xff\xe1".pack('n', strlen($exif) + 2).$exif.substr($jpeg, 2);

    [$bytes, $format] = app(ImageOptimizer::class)->optimise($jpeg);
    $result = imagecreatefromstring($bytes);
    $corners = [
        'top-left' => [$width / 4, $height / 4], 'top-right' => [$width * 3 / 4, $height / 4],
        'bottom-right' => [$width * 3 / 4, $height * 3 / 4], 'bottom-left' => [$width / 4, $height * 3 / 4],
    ];
    $red = array_keys(array_filter($corners, fn (array $point): bool => ((imagecolorat($result, (int) $point[0], (int) $point[1]) >> 16) & 0xFF) > 200));
    expect($format)->toBe('jpg')->and([imagesx($result), imagesy($result)])->toBe([$width, $height])
        ->and($red)->toBe([$redCorner]);
})->with([
    'normal' => [1, 80, 40, 'top-left'],
    'mirrored' => [2, 80, 40, 'top-right'],
    'upside down' => [3, 80, 40, 'bottom-right'],
    'mirrored upside down' => [4, 80, 40, 'bottom-left'],
    'transposed' => [5, 40, 80, 'top-left'],
    'turned clockwise' => [6, 40, 80, 'top-right'],
    'transversed' => [7, 40, 80, 'bottom-right'],
    'turned anticlockwise' => [8, 40, 80, 'bottom-left'],
]);

test('transparent images keep alpha and appended data is stripped', function (string $format) {
    $image = imagecreatetruecolor(30, 20);
    imagealphablending($image, false);
    imagesavealpha($image, true);
    imagefill($image, 0, 0, imagecolorallocatealpha($image, 0, 0, 0, 127));
    ob_start();
    imagepng($image);
    $input = ob_get_clean().'<?php appended(); ?>';
    [$bytes, $extension] = app(ImageOptimizer::class)->optimise($input, $format);
    expect($extension)->toBe($format === 'webp' && ! function_exists('imagewebp') ? 'png' : $format);
    $result = imagecreatefromstring($bytes);
    expect($bytes)->not->toContain('<?php')->and((imagecolorat($result, 0, 0) >> 24) & 127)->toBe(127);
})->with(['png', 'webp']);

test('legacy optimization opt-out cannot bypass limits', function () {
    DB::table('business_settings')->insert(['type' => 'disable_image_optimization', 'value' => '1']);
    $owner = createStoreUser('customer');
    withScratchPublicPath(function (string $publicPath) use ($owner) {
        $upload = app(MultipartUploadService::class)->store(UploadedFile::fake()->image('large.png', 3000, 2000), $owner->id);
        $info = getimagesize($publicPath.'/'.$upload->file_name);
        expect($info[0])->toBe(1500)->and($info[1])->toBe(1000)
            ->and($upload->file_size)->toBeLessThanOrEqual(ImageOptimizer::MAX_OUTPUT_BYTES);
    });
});

test('private document images are optimized and stay private', function (string $storage, string $directory) {
    Storage::fake('local');
    $name = app($storage)->store(UploadedFile::fake()->image('scan.jpg', 3000, 2000));
    $bytes = Storage::disk('local')->get($directory.'/'.$name);
    expect(getimagesizefromstring($bytes)[0])->toBe(2400)
        ->and(strlen($bytes))->toBeLessThanOrEqual(ImageOptimizer::MAX_OUTPUT_BYTES);
})->with([
    [TradeLicenseStorage::class, 'uploads/licenses'],
    [ShopVerificationStorage::class, 'uploads/verification_form'],
]);

test('invalid images and pixel bombs are rejected', function (string $bytes) {
    expect(fn () => app(ImageOptimizer::class)->optimise($bytes))->toThrow(InvalidArgumentException::class);
})->with([
    'corrupt' => ['not an image'],
    'pixel bomb' => [substr_replace(base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAQAAAAECAIAAAAmkwkpAAAADElEQVQImWNgIB0AAAA0AAGsXG5DAAAAAElFTkSuQmCC'), pack('NN', 10000, 10000), 16, 8)],
]);

test('large lossless images shrink until they meet the storage budget', function () {
    $image = imagecreatetruecolor(1800, 1800);
    for ($row = 0; $row < 1800; $row++) {
        for ($column = 0; $column < 1800; $column++) {
            imagesetpixel($image, $column, $row, ($column * 7919 + $row * 104729) & 0xFFFFFF);
        }
    }
    ob_start();
    imagepng($image, null, 0);
    $input = ob_get_clean();
    [$bytes] = app(ImageOptimizer::class)->optimise($input);
    expect(strlen($input))->toBeGreaterThan(ImageOptimizer::MAX_OUTPUT_BYTES)
        ->and(strlen($bytes))->toBeLessThanOrEqual(ImageOptimizer::MAX_OUTPUT_BYTES)
        ->and(getimagesizefromstring($bytes)[0])->toBeLessThanOrEqual(1500);
});

test('jpeg EXIF orientation is applied before metadata is removed', function () {
    $file = UploadedFile::fake()->image('portrait.jpg', 80, 40);
    $exif = "Exif\0\0II\x2a\0\x08\0\0\0\x01\0\x12\x01\x03\0\x01\0\0\0\x06\0\0\0\0\0\0\0";
    $jpeg = $file->getContent();
    $jpeg = substr($jpeg, 0, 2)."\xff\xe1".pack('n', strlen($exif) + 2).$exif.substr($jpeg, 2);
    [$bytes] = app(ImageOptimizer::class)->optimise($jpeg);
    $info = getimagesizefromstring($bytes);
    expect($info[0])->toBe(40)->and($info[1])->toBe(80)->and($bytes)->not->toContain('Exif');
});

test('animated images are refused instead of silently losing frames', function () {
    $gif = base64_decode('R0lGODlhAQABAIAAAAAAAP///ywAAAAAAQABAAACAUwAOw==');
    $frame = substr($gif, 19, -1);
    $animated = substr($gif, 0, -1).$frame."\x3b";
    expect(fn () => app(ImageOptimizer::class)->optimise($animated))
        ->toThrow(InvalidArgumentException::class, 'Animated images are not supported.');
    [$bytes, $format] = app(ImageOptimizer::class)->optimise($gif);
    expect($format)->toBe('gif')->and(getimagesizefromstring($bytes)[0])->toBe(1);
});

test('all base64 API image endpoints enforce dimensions on direct uploads', function (string $endpoint) {
    $owner = createStoreUser('customer');
    $file = UploadedFile::fake()->image('large.jpg', 2400, 1600);
    withScratchPublicPath(function (string $publicPath) use ($owner, $file, $endpoint) {
        $this->withToken(storeApiToken($owner))->postJson('/api/v1/store/'.$endpoint, [
            'image' => base64_encode($file->getContent()), 'filename' => 'large.jpg',
        ])->assertOk()->assertJsonPath('result', true);
        $upload = Upload::sole();
        $info = getimagesize($publicPath.'/'.$upload->file_name);
        expect($info[0])->toBe(1500)->and($info[1])->toBe(1000)
            ->and($upload->file_size)->toBeLessThanOrEqual(ImageOptimizer::MAX_OUTPUT_BYTES);
    });
})->with(['file/image-upload', 'profile/update-image', 'profile/image-upload']);

test('a minimum dimension stops the shrinking for the byte budget there, and returns that size even when larger', function () {
    $photo = billPhoto(2000, 1500, noise: 255);

    [$floored] = app(ImageOptimizer::class)->optimise($photo, 'jpg', 2400, 80, minDimension: 1600);
    [$budgeted] = app(ImageOptimizer::class)->optimise($photo, 'jpg', 2400, 80);

    expect(getimagesizefromstring($floored)[0])->toBe(1600)
        ->and(strlen($floored))->toBeGreaterThan(ImageOptimizer::MAX_OUTPUT_BYTES)
        ->and(getimagesizefromstring($budgeted)[0])->toBeLessThan(1600)
        ->and(strlen($budgeted))->toBeLessThanOrEqual(ImageOptimizer::MAX_OUTPUT_BYTES);
});

test('a lower quality gives a smaller JPEG', function () {
    $photo = billPhoto(800, 600);

    [$high] = app(ImageOptimizer::class)->optimise($photo, 'jpg', 2400, 95);
    [$low] = app(ImageOptimizer::class)->optimise($photo, 'jpg', 2400, 60);

    expect(strlen($low))->toBeLessThan(strlen($high));
});
