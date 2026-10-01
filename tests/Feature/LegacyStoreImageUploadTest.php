<?php

use App\Models\Upload;
use App\Services\Base64ImageUploadService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\File;

/**
 * The store API's base64 image uploads wrote the request's bytes to
 * `uploads/all/<filename>` with the filename from the request, and looked at
 * the extension only afterwards, deleting the file again if it was not an
 * image: a `../` in the name wrote (and then deleted) a file anywhere the web
 * user could reach, `index.php` included, and a PHP file sat on disk until the
 * check ran. Any self-registered customer could call it. Base64ImageUploadService
 * is the one place that stores them now.
 *
 * Each test points the public path at a scratch directory with a sentinel
 * `index.php` beside the upload directory, so a regression writes there and
 * never into the real `public/`.
 */
function withScratchPublicPath(Closure $callback): void
{
    $root = sys_get_temp_dir().'/image-upload-'.bin2hex(random_bytes(4));
    File::ensureDirectoryExists("$root/public/uploads/all");
    file_put_contents("$root/public/index.php", '<?php // sentinel');
    $originalPublicPath = app()->publicPath();
    app()->usePublicPath("$root/public");

    try {
        $callback("$root/public");
    } finally {
        app()->usePublicPath($originalPublicPath);
        File::deleteDirectory($root);
    }
}

function tinyPng(): string
{
    $image = imagecreatetruecolor(4, 4);
    ob_start();
    imagepng($image);

    return (string) ob_get_clean();
}

/**
 * @return list<string> The names of the files in the scratch upload directory.
 */
function storedUploadFiles(string $publicPath): array
{
    return array_map('basename', glob("$publicPath/uploads/all/*") ?: []);
}

test('a valid image is stored under a server-chosen name and recorded', function () {
    $customer = createStoreUser('customer');

    withScratchPublicPath(function (string $publicPath) use ($customer) {
        $response = $this->withToken(storeApiToken($customer))
            ->postJson('/temp/api/v2/file/image-upload', ['image' => base64_encode(tinyPng()), 'filename' => 'holiday photo.png'])
            ->assertOk()
            ->assertJsonPath('result', true);

        $upload = Upload::findOrFail($response->json('upload_id'));
        $stored = storedUploadFiles($publicPath);

        expect($stored)->toHaveCount(1)
            ->and($upload->file_name)->toBe('uploads/all/'.$stored[0])
            ->and($stored[0])->toMatch('/^[A-Za-z0-9]{40}\.png$/')
            ->and($upload->extension)->toBe('png')
            ->and($upload->type)->toBe('image')
            ->and($upload->file_original_name)->toBe('holiday photo')
            ->and($upload->user_id)->toEqual($customer->id)
            ->and(getimagesize("$publicPath/{$upload->file_name}")['mime'])->toBe('image/png');
    });
});

test('an upload with no token is refused and stores nothing', function () {
    withScratchPublicPath(function (string $publicPath) {
        $this->postJson('/temp/api/v2/file/image-upload', ['image' => base64_encode(tinyPng()), 'filename' => 'a.png'])->assertUnauthorized();

        expect(storedUploadFiles($publicPath))->toBe([])
            ->and(Upload::count())->toBe(0);
    });
});

test('PHP source is refused whatever it is called, and nothing is written', function (string $filename) {
    $customer = createStoreUser('customer');

    withScratchPublicPath(function (string $publicPath) use ($customer, $filename) {
        $this->withToken(storeApiToken($customer))
            ->postJson('/temp/api/v2/file/image-upload', ['image' => base64_encode('<?php system($_GET[1]); ?>'), 'filename' => $filename])
            ->assertOk()
            ->assertJsonPath('result', false);

        expect(storedUploadFiles($publicPath))->toBe([])
            ->and(Upload::count())->toBe(0);
    });
})->with(['a php name' => ['shell.php'], 'a png name' => ['shell.png'], 'a double extension' => ['shell.php.png'], 'no name' => ['']]);

test('an image with code appended to it is stored without the code', function () {
    $customer = createStoreUser('customer');

    withScratchPublicPath(function (string $publicPath) use ($customer) {
        $this->withToken(storeApiToken($customer))
            ->postJson('/temp/api/v2/file/image-upload', ['image' => base64_encode(tinyPng().'<?php evil(); ?>'), 'filename' => 'a.png'])
            ->assertOk()
            ->assertJsonPath('result', true);

        $stored = storedUploadFiles($publicPath);

        expect($stored)->toHaveCount(1)
            ->and(file_get_contents("$publicPath/uploads/all/{$stored[0]}"))->not->toContain('<?php')
            ->and(getimagesize("$publicPath/uploads/all/{$stored[0]}")[0])->toBe(4);
    });
});

test('an svg is stored without its script', function () {
    $customer = createStoreUser('customer');
    $svg = '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script><rect width="1" height="1" onclick="alert(2)"/></svg>';

    withScratchPublicPath(function (string $publicPath) use ($customer, $svg) {
        $this->withToken(storeApiToken($customer))
            ->postJson('/temp/api/v2/file/image-upload', ['image' => base64_encode($svg), 'filename' => 'logo.svg'])
            ->assertOk()
            ->assertJsonPath('result', true);

        $stored = storedUploadFiles($publicPath);
        $contents = file_get_contents("$publicPath/uploads/all/{$stored[0]}");

        expect($stored[0])->toEndWith('.svg')
            ->and($contents)->toContain('<rect')
            ->and($contents)->not->toContain('<script')
            ->and($contents)->not->toContain('onclick');
    });
});

test('input that is not an image is refused with a message and stores nothing', function (string $image, string $message) {
    $customer = createStoreUser('customer');

    withScratchPublicPath(function (string $publicPath) use ($customer, $image, $message) {
        $this->withToken(storeApiToken($customer))
            ->postJson('/temp/api/v2/file/image-upload', ['image' => $image, 'filename' => 'a.png'])
            ->assertOk()
            ->assertJsonPath('result', false)
            ->assertJsonPath('message', $message);

        expect(storedUploadFiles($publicPath))->toBe([]);
    });
})->with([
    'not base64' => ['@@@ not base64 @@@', 'The image is not valid base64.'],
    'text called a png' => [base64_encode('hello'), 'Only image can be uploaded'],
]);

test('the service never builds a path from the client\'s filename', function (string $hostile) {
    $customer = createStoreUser('customer');

    withScratchPublicPath(function (string $publicPath) use ($customer, $hostile) {
        $upload = app(Base64ImageUploadService::class)->store(base64_encode(tinyPng()), $hostile, $customer->id);

        // The sentinel beside `uploads/` is untouched, and nothing but the
        // one random-named file was written anywhere under the scratch root.
        expect(file_get_contents("$publicPath/index.php"))->toBe('<?php // sentinel')
            ->and(storedUploadFiles($publicPath))->toHaveCount(1)
            ->and($upload->file_name)->toMatch('#^uploads/all/[A-Za-z0-9]{40}\.png$#')
            ->and(scandir(dirname($publicPath)))->toBe(['.', '..', 'public'])
            ->and($upload->file_original_name)->not->toContain('/');
    });
})->with(['parent directories' => ['../../index.php'], 'an absolute path' => ['/etc/cron.d/x.png'], 'windows separators' => ['..\\..\\index.php'], 'a null byte' => ["a.png\0.php"]]);

test('an image over the size limit, or with too many pixels, is refused', function () {
    $customer = createStoreUser('customer');
    $service = app(Base64ImageUploadService::class);

    $widePng = substr_replace(tinyPng(), pack('NN', 10000, 10000), 16, 8);

    withScratchPublicPath(function (string $publicPath) use ($customer, $service, $widePng) {
        expect(fn () => $service->store(base64_encode(str_repeat('A', Base64ImageUploadService::MAX_BYTES + 1)), 'big.png', $customer->id))
            ->toThrow(InvalidArgumentException::class, 'The image is too large.')
            ->and(fn () => $service->store(base64_encode($widePng), 'wide.png', $customer->id))
            ->toThrow(InvalidArgumentException::class, 'The image is too large.');

        expect(storedUploadFiles($publicPath))->toBe([]);
    });
});

test('the profile image endpoints store through the same service', function () {
    $customer = createStoreUser('customer');
    $token = storeApiToken($customer);

    withScratchPublicPath(function (string $publicPath) use ($customer, $token) {
        $avatar = $this->withToken($token)
            ->postJson('/temp/api/v2/profile/update-image', ['image' => base64_encode(tinyPng()), 'filename' => 'avatar.png'])
            ->assertOk()
            ->assertJsonPath('result', true);

        Auth::forgetGuards();

        $other = $this->withToken($token)
            ->postJson('/temp/api/v2/profile/image-upload', ['image' => base64_encode(tinyPng()), 'filename' => 'x.png'])
            ->assertOk()
            ->assertJsonPath('result', true);

        expect($customer->fresh()->avatar_original)->not->toBeNull()
            ->and(Upload::count())->toBe(2)
            ->and(storedUploadFiles($publicPath))->toHaveCount(2)
            ->and($other->json('upload_id'))->not->toBeNull()
            ->and($avatar->json('path'))->toBeString();
    });
});
