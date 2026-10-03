<?php

use App\Models\Upload;
use Illuminate\Http\UploadedFile;

function uploadedFile(string $name, string $contents): UploadedFile
{
    return UploadedFile::fake()->createWithContent($name, $contents);
}

test('all_file route rejects GET requests', function () {
    $admin = createStoreUser('admin');
    actingAsStoreUser($admin);

    $response = $this->get('/admin/all-file');

    expect($response->getStatusCode())->toBeIn([404, 405]);
});

test('all_file route requires POST method', function () {
    $admin = createStoreUser('admin');
    actingAsStoreUser($admin);

    $response = $this->post('/admin/all-file');

    expect($response->getStatusCode())->toBeIn([200, 302]);
});

test('all_file route rejects users without permission', function () {
    $customer = createStoreUser('customer');
    actingAsStoreUser($customer);

    $response = $this->post('/admin/all-file');

    expect($response->getStatusCode())->toBeIn([302, 403]);
});

test('oversized uploads are rejected', function () {
    $customer = createStoreUser('customer');

    withScratchPublicPath(function (string $publicPath) use ($customer) {
        $this->withToken(storeApiToken($customer))
            ->postJson('/temp/api/v2/file/upload', [
                'aiz_file' => uploadedFile('huge.png', str_repeat('x', 513 * 1024 * 1024)),
            ])
            ->assertOk();

        expect(Upload::count())->toBe(0);
    });
});

test('swf files are rejected', function () {
    $customer = createStoreUser('customer');

    withScratchPublicPath(function (string $publicPath) use ($customer) {
        $this->withToken(storeApiToken($customer))
            ->postJson('/temp/api/v2/file/upload', [
                'aiz_file' => uploadedFile('animation.swf', 'FWS...'),
            ])
            ->assertOk();

        expect(Upload::count())->toBe(0);
    });
});

test('xml files are rejected', function () {
    $customer = createStoreUser('customer');

    withScratchPublicPath(function (string $publicPath) use ($customer) {
        $this->withToken(storeApiToken($customer))
            ->postJson('/temp/api/v2/file/upload', [
                'aiz_file' => uploadedFile('data.xml', '<?xml version="1.0"?><root></root>'),
            ])
            ->assertOk();

        expect(Upload::count())->toBe(0);
    });
});
