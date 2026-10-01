<?php

use App\Models\Upload;
use App\Services\MultipartUploadService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;

/**
 * The store's uploader (the web screens and the mobile APIs) let the client's
 * extension pick the allowlist and then stored the file under the extension its
 * *content* looked like: an `a.png` that held HTML became `<random>.html`, and one
 * that held an SVG became `<random>.svg`, served from the shop's own origin. The
 * three controllers each carried a copy; MultipartUploadService is the one place
 * now. Each test points the public path at a scratch directory.
 */
function uploadedFile(string $name, string $contents): UploadedFile
{
    return UploadedFile::fake()->createWithContent($name, $contents);
}

test('an image is stored under a random name with the extension its content has, and recorded', function () {
    $customer = createStoreUser('customer');

    withScratchPublicPath(function (string $publicPath) use ($customer) {
        $this->withToken(storeApiToken($customer))
            ->postJson('/temp/api/v2/file/upload', ['aiz_file' => uploadedFile('holiday photo.PNG', tinyPng())])
            ->assertOk();

        $stored = storedUploadFiles($publicPath);
        $upload = Upload::sole();

        expect($stored)->toHaveCount(1)
            ->and($stored[0])->toMatch('/^[A-Za-z0-9]{40}\.png$/')
            ->and($upload->file_name)->toBe('uploads/all/'.$stored[0])
            ->and($upload->extension)->toBe('png')
            ->and($upload->type)->toBe('image')
            ->and($upload->file_original_name)->toBe('holiday photo')
            ->and($upload->user_id)->toEqual($customer->id)
            ->and(getimagesize("$publicPath/{$upload->file_name}")['mime'])->toBe('image/png');
    });
});

test('markup a browser would run is refused whatever the file is called', function (string $filename, string $contents) {
    $customer = createStoreUser('customer');

    withScratchPublicPath(function (string $publicPath) use ($customer, $filename, $contents) {
        $this->withToken(storeApiToken($customer))
            ->postJson('/temp/api/v2/file/upload', ['aiz_file' => uploadedFile($filename, $contents)])
            ->assertOk();

        expect(storedUploadFiles($publicPath))->toBe([])
            ->and(Upload::count())->toBe(0);
    });
})->with([
    'html called png' => ['a.png', '<html><script>alert(1)</script></html>'],
    'html called txt' => ['notes.txt', '<!DOCTYPE html><html><body onload="alert(1)"></body></html>'],
    'xhtml called xml' => ['feed.xml', '<?xml version="1.0"?><html xmlns="http://www.w3.org/1999/xhtml"><script>alert(1)</script></html>'],
    'svg-in-xml called xml' => ['feed.xml', '<svg xmlns="http://www.w3.org/2000/svg" onload="alert(1)"/>'],
    'a file that is not an image called png' => ['a.png', 'plain text, not an image'],
    'an extension off the list' => ['page.html', '<p>hi</p>'],
    'php called php' => ['shell.php', '<?php system($_GET[1]); ?>'],
]);

test('PHP source that gets through the allowlist is never stored with a PHP extension', function (string $filename) {
    $customer = createStoreUser('customer');

    withScratchPublicPath(function (string $publicPath) use ($customer, $filename) {
        $this->withToken(storeApiToken($customer))
            ->postJson('/temp/api/v2/file/upload', ['aiz_file' => uploadedFile($filename, '<?php system($_GET[1]); ?>')])
            ->assertOk();

        foreach (storedUploadFiles($publicPath) as $stored) {
            expect($stored)->not->toMatch('/\.(php\d?|phtml|phar)$/i');
        }

        expect(file_get_contents("$publicPath/index.php"))->toBe('<?php // sentinel');
    });
})->with(['a txt' => ['shell.txt'], 'a csv' => ['shell.csv'], 'a pdf' => ['shell.pdf'], 'a double extension' => ['shell.php.txt']]);

test('an svg is sanitised wherever it turns up, including under another name and through the API', function (string $filename) {
    $customer = createStoreUser('customer');
    $svg = '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script><rect width="1" height="1" onclick="alert(2)"/></svg>';

    withScratchPublicPath(function (string $publicPath) use ($customer, $filename, $svg) {
        $this->withToken(storeApiToken($customer))
            ->postJson('/temp/api/v2/file/upload', ['aiz_file' => uploadedFile($filename, $svg)])
            ->assertOk();

        $stored = storedUploadFiles($publicPath);
        $contents = file_get_contents("$publicPath/uploads/all/{$stored[0]}");

        expect($stored)->toHaveCount(1)
            ->and($stored[0])->toEndWith('.svg')
            ->and($contents)->toContain('<rect')
            ->and($contents)->not->toContain('<script')
            ->and($contents)->not->toContain('onclick')
            ->and(Upload::sole()->extension)->toBe('svg');
    });
})->with(['an svg' => ['logo.svg'], 'a png' => ['logo.png'], 'a txt' => ['logo.txt']]);

test('a document keeps its allowlisted extension and kind', function () {
    $customer = createStoreUser('customer');

    withScratchPublicPath(function (string $publicPath) use ($customer) {
        $this->withToken(storeApiToken($customer))
            ->postJson('/temp/api/v2/file/upload', ['aiz_file' => uploadedFile('price list.CSV', "sku,price\nA1,10\n")])
            ->assertOk();

        $stored = storedUploadFiles($publicPath);

        expect($stored[0])->toMatch('/^[A-Za-z0-9]{40}\.csv$/')
            ->and(Upload::sole()->type)->toBe('document')
            ->and(Upload::sole()->file_original_name)->toBe('price list');
    });
});

test('the service never builds a path from the client\'s filename', function (string $hostile) {
    $customer = createStoreUser('customer');

    withScratchPublicPath(function (string $publicPath) use ($customer, $hostile) {
        $upload = app(MultipartUploadService::class)->store(uploadedFile($hostile, tinyPng()), $customer->id);

        expect(file_get_contents("$publicPath/index.php"))->toBe('<?php // sentinel')
            ->and(storedUploadFiles($publicPath))->toHaveCount(1)
            ->and($upload->file_name)->toMatch('#^uploads/all/[A-Za-z0-9]{40}\.png$#')
            ->and(scandir(dirname($publicPath)))->toBe(['.', '..', 'public']);
    });
})->with(['parent directories' => ['../../index.php.png'], 'an absolute path' => ['/etc/cron.d/x.png'], 'windows separators' => ['..\\..\\index.php.png']]);

test('the web uploader needs a signed-in account and stores for it', function () {
    $customer = createStoreUser('customer');

    withScratchPublicPath(function (string $publicPath) use ($customer) {
        expect($this->post('/temp/aiz-uploader/upload', ['aiz_file' => uploadedFile('a.png', tinyPng())])->getStatusCode())->toBeIn([302, 401, 403])
            ->and(Upload::count())->toBe(0)
            ->and(storedUploadFiles($publicPath))->toBe([]);

        actingAsStoreUser($customer);

        $this->post('/temp/aiz-uploader/upload', ['aiz_file' => uploadedFile('a.png', tinyPng())])->assertOk();

        expect(Upload::sole()->user_id)->toEqual($customer->id)
            ->and(storedUploadFiles($publicPath))->toHaveCount(1);
    });
});

test('the web uploader hands another account\'s files to no one but admin and staff', function () {
    $owner = createStoreUser('customer');
    $stranger = createStoreUser('customer');
    $theirs = Upload::factory()->stored('uploads/all/theirs.png')->create(['user_id' => $owner->id, 'type' => 'image', 'extension' => 'png']);
    $mine = Upload::factory()->stored('uploads/all/mine.png')->create(['user_id' => $stranger->id, 'type' => 'image', 'extension' => 'png']);

    withScratchPublicPath(function (string $publicPath) use ($owner, $stranger, $theirs, $mine) {
        file_put_contents("$publicPath/uploads/all/theirs.png", tinyPng());
        actingAsStoreUser($stranger);

        $this->get("/temp/aiz-uploader/download/{$theirs->id}")->assertForbidden();
        $this->post('/temp/aiz-uploader/get_file_by_ids', ['ids' => "{$theirs->id},{$mine->id}"])
            ->assertOk()
            ->assertJsonCount(1)
            ->assertJsonPath('0.id', $mine->id);

        expect($stranger->can('view', $theirs))->toBeFalse()
            ->and($stranger->can('delete', $theirs))->toBeFalse()
            ->and($owner->can('delete', $theirs))->toBeTrue()
            ->and(createStoreUser('admin')->can('delete', $theirs))->toBeTrue()
            ->and(createStoreUser('staff')->can('view', $theirs))->toBeTrue();

        Auth::forgetGuards();
        actingAsStoreUser($owner);

        expect($this->get("/temp/aiz-uploader/download/{$theirs->id}")->getStatusCode())->not->toBe(403);
    });
});
